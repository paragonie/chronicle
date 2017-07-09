<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\AccessDenied,
    Exception\ClientNotFound,
    Exception\HTTPException,
    HandlerInterface,
    Scheduled
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};
use Slim\Http\Request;

/**
 * Class Revoke
 * @package ParagonIE\Chronicle\Handlers
 */
class Revoke implements HandlerInterface
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
     * @throws ClientNotFound
     * @throws HTTPException
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
            throw new \TypeError('Something unexpected happen when attempting to revoke.');
        }

        /* Revoking a public key cannot be replayed. */
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
            throw new HTTPException('Empty post body');
        }
        if (empty($post['clientid'])) {
            throw new ClientNotFound('Error: Client ID expected');
        }
        if (empty($post['publickey'])) {
            throw new ClientNotFound('Error: Public key expected');
        }

        $db = Chronicle::getDatabase();
        $db->beginTransaction();
        $db->delete(
            'chronicle_clients',
            [
                'publicid' => $post['clientid'],
                'publickey' => $post['publickey'],
                'isAdmin' => false
            ]
        );
        if ($db->commit()) {
            // Confirm deletion:
            $result = [
                'deleted' => !$db->exists('SELECT count(id) FROM chronicle_clients WHERE publicid = ?', $post['clientid'])
            ];

            if (!$result['deleted']) {
                $isAdmin = $db->cell('SELECT isAdmin FROM chronicle_clients WHERE publicid = ?', $post['clientid']);
                $result['reason'] = !empty($isAdmin)
                    ? 'You cannot delete administrators from this API'
                    : 'Unknown';
            }
            $now = (new \DateTime())->format(\DateTime::ATOM);

            $settings = Chronicle::getSettings();
            if (!empty($settings['publish-revoked-clients'])) {
                $serverKey = Chronicle::getSigningKey();
                $message = \json_encode(
                    [
                        'server-action' => 'Client Access Revocation',
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
                $result['revoke'] = Chronicle::extendBlakechain(
                    $signature,
                    $message,
                    $serverKey->getPublicKey()
                );

                // If we need to do a cross-sign, do it now:
                (new Scheduled())->doCrossSigns();
            }
        } else {
            /* PDO should have already thrown an exception. */
            $db->rollBack();
            throw new \PDOException($db->errorInfo()[0]);
        }

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $result
            ],
            Chronicle::getSigningKey(),
            $response->getHeaders(),
            $response->getProtocolVersion()
        );
    }
}
