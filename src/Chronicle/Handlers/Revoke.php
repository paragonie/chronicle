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
            return Chronicle::errorResponse($response, 'POST body empty or invalid', 406);
        }
        if (empty($post['clientid'])) {
            return Chronicle::errorResponse($response, 'Error: Client ID expected', 401);
        }
        if (empty($post['publickey'])) {
            return Chronicle::errorResponse($response, 'Error: Public key expected', 401);
        }

        $db = Chronicle::getDatabase();
        $db->beginTransaction();

        $found = $db->exists(
            'SELECT count(id) FROM chronicle_clients WHERE publicid = ? AND publickey = ?',
            $post['clientid'],
            $post['publickey']
        );
        if (!$found) {
            return Chronicle::errorResponse($response, 'Error: Client not found. It may have already been deleted.', 404);
        }
        $isAdmin = $db->cell(
            'SELECT isAdmin FROM chronicle_clients WHERE publicid = ? AND publickey = ?',
            $post['clientid'],
            $post['publickey']
        );
        if ($isAdmin) {
            return Chronicle::errorResponse($response, 'You cannot delete administrators from this API.', 403);
        }

        $db->delete(
            'chronicle_clients',
            [
                'publicid' => $post['clientid'],
                'publickey' => $post['publickey'],
                'isAdmin' => Chronicle::getDatabaseBoolean(false)
            ]
        );
        if ($db->commit()) {
            // Confirm deletion:
            $result = [
                'deleted' => !$db->exists(
                    'SELECT count(id) FROM chronicle_clients WHERE publicid = ? AND publickey = ?',
                    $post['clientid'],
                    $post['publickey']
                )
            ];

            if (!$result['deleted']) {
                $result['reason'] = 'Delete operatio nwas unsuccessful due to unknown reasons.';
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
            return Chronicle::errorResponse(
                $response,
                $db->errorInfo()[0],
                500
            );
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
