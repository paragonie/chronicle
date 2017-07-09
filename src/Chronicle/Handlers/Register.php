<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\AccessDenied,
    Exception\HTTPException,
    Exception\SecurityViolation,
    HandlerInterface,
    Scheduled
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};
use Slim\Http\Request;

/**
 * Class Register
 * @package ParagonIE\Chronicle\Handlers
 */
class Register implements HandlerInterface
{
    /**
     * The handler gets invoked by the router. This accepts a Request
     * and returns a Response.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     *
     * @throws AccessDenied
     * @throws HTTPException
     * @throws SecurityViolation
     * @throws \TypeError
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        // Sanity checks:
        if ($request instanceof Request) {
            if (!$request->getAttribute('authenticated')) {
                throw new AccessDenied('Unauthenticated request');
            }
            if (!$request->getAttribute('administrator')) {
                throw new AccessDenied('Unprivileged request');
            }
        } else {
            throw new \TypeError('Something unexpected happen when attempting to register.');
        }

        try {
            Chronicle::validateTimestamps($request);
        } catch (\Throwable $ex) {
            return Chronicle::errorResponse(
                $response,
                $ex->getMessage(),
                $ex->getCode()
            );
        }

        // Get the parsed POST body:
        $post = $request->getParsedBody();
        if (!\is_array($post)) {
            throw new HTTPException('POST body empty or invalid');
        }
        if (empty($post['publickey'])) {
            throw new SecurityViolation('Error: Public key expected');
        }

        // If this is not a valid public key, let the exception be uncaught:
        new SigningPublicKey(Base64UrlSafe::decode($post['publickey']));

        $result = [
            'client-id' => $this->createClient($post)
        ];

        $now = (new \DateTime())->format(\DateTime::ATOM);

        $settings = Chronicle::getSettings();
        if (!empty($settings['publish-new-clients'])) {
            $serverKey = Chronicle::getSigningKey();
            $message = \json_encode(
                [
                    'server-action' => 'New Client Registration',
                    'now' => $now,
                    'clientid' => $result['client-id'],
                    'publickey' => $post['publickey']
                ],
                JSON_PRETTY_PRINT
            );
            $signature = Base64UrlSafe::encode(
                \ParagonIE_Sodium_Compat::crypto_sign_detached(
                    $message,
                    $serverKey->getString(true)
                )
            );
            $result['publish'] = Chronicle::extendBlakechain(
                $signature,
                $message,
                $serverKey->getPublicKey()
            );

            // If we need to do a cross-sign, do it now:
            (new Scheduled())->doCrossSigns();
        }

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => $now,
                'status' => 'OK',
                'results' => $result
            ],
            Chronicle::getSigningKey(),
            $response->getHeaders(),
            $response->getProtocolVersion()
        );
    }

    /**
     * Registers a new, non-administrator client that can commit messages.
     *
     * @param array $post
     * @return string
     * @throws \PDOException
     */
    protected function createClient(array $post): string
    {
        $db = Chronicle::getDatabase();
        $now = (new \DateTime())->format(\DateTime::ATOM);

        do {
            $clientId = Base64UrlSafe::encode(\random_bytes(24));
        } while ($db->cell('SELECT count(id) FROM chronicle_clients WHERE publicid = ?', $clientId) > 0);

        $db->beginTransaction();
        $db->insert(
            'chronicle_clients',
            [
                'publicid' => $clientId,
                'publickey' => $post['publickey'],
                'comment' => $post['comment'] ?? '',
                'created' => $now,
                'modified' => $now
            ]
        );
        if (!$db->commit()) {
            $db->rollBack();
            throw new \PDOException($db->errorInfo()[0]);
        }
        return $clientId;
    }
}
