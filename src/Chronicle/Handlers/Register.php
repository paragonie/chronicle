<?php
namespace ParagonIE\Chronicle\Handlers;

use GuzzleHttp\Exception\GuzzleException;
use ParagonIE\Chronicle\{
    Chronicle,
    Exception\ChainAppendException,
    Exception\FilesystemException,
    Exception\SecurityViolation,
    Exception\TargetNotFound,
    HandlerInterface,
    Scheduled
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\Exception\InvalidMessageException;
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
     * @throws ChainAppendException
     * @throws FilesystemException
     * @throws GuzzleException
     * @throws InvalidMessageException
     * @throws SecurityViolation
     * @throws \SodiumException
     * @throws TargetNotFound
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        // Sanity checks:
        if ($request instanceof Request) {
            if (!$request->getAttribute('authenticated')) {
                return Chronicle::errorResponse(
                    $response,
                    'Unauthenticated request',
                    401
                );
            }
            if (!$request->getAttribute('administrator')) {
                return Chronicle::errorResponse(
                    $response,
                    'Unprivileged request',
                   403
                );
            }
        } else {
            return Chronicle::errorResponse(
                $response,
                'Something unexpected happen when attempting to register.',
                500
            );
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
        /** @var array<string, string> $post */
        $post = $request->getParsedBody();
        if (!\is_array($post)) {
            return Chronicle::errorResponse($response, 'POST body empty or invalid', 406);
        }
        try {
            if (empty($post['publickey'])) {
                throw new SecurityViolation('Error: Public key expected');
            }

            // If this is not a valid public key, let the exception be uncaught:
            new SigningPublicKey(Base64UrlSafe::decode($post['publickey']));
        } catch (\Throwable $ex) {
            return Chronicle::errorResponse(
                $response,
                $ex->getMessage(),
                500
            );
        }

        $result = [
            'client-id' => $this->createClient($post)
        ];

        $now = (new \DateTime())->format(\DateTime::ATOM);

        $settings = Chronicle::getSettings();
        if (!empty($settings['publish-new-clients'])) {
            $serverKey = Chronicle::getSigningKey();
            /** @var string $message */
            $message = \json_encode(
                [
                    'server-action' => 'New Client Registration',
                    'now' => $now,
                    'clientid' => $result['client-id'],
                    'publickey' => $post['publickey']
                ],
                JSON_PRETTY_PRINT
            );
            if (!\is_string($message)) {
                throw new \TypeError('Invalid messsage');
            }
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
     *
     * @throws \PDOException
     * @throws SecurityViolation
     */
    protected function createClient(array $post): string
    {
        $db = Chronicle::getDatabase();
        $now = (new \DateTime())->format(\DateTime::ATOM);
        $queryString = 'SELECT count(id) FROM ' .
            Chronicle::getTableName('clients') .
            ' WHERE publicid = ?';

        do {
            try {
                $clientId = Base64UrlSafe::encode(\random_bytes(24));
            } catch (\Throwable $ex) {
                throw new SecurityViolation('CSPRNG is broken');
            }
        } while ($db->exists($queryString, $clientId));

        $db->beginTransaction();
        $db->insert(
            Chronicle::getTableName('clients', true),
            [
                'publicid' => $clientId,
                'publickey' => $post['publickey'],
                'comment' => $post['comment'] ?? '',
                'isAdmin' => false,
                'created' => $now,
                'modified' => $now
            ]
        );
        if (!$db->commit()) {
            $db->rollBack();
            /** @var array<int, string> $errorInfo */
            $errorInfo = $db->errorInfo();
            throw new \PDOException($errorInfo[0]);
        }
        return $clientId;
    }
}
