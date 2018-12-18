<?php
namespace ParagonIE\Chronicle\Handlers;

use GuzzleHttp\Exception\GuzzleException;
use ParagonIE\Chronicle\{
    Chronicle,
    Exception\AccessDenied,
    Exception\BaseException,
    Exception\FilesystemException,
    Exception\InvalidInstanceException,
    Exception\TargetNotFound,
    HandlerInterface,
    Scheduled
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Exception\InvalidMessageException;
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
     * @throws BaseException
     * @throws FilesystemException
     * @throws GuzzleException
     * @throws InvalidInstanceException
     * @throws InvalidMessageException
     * @throws TargetNotFound
     * @throws \SodiumException
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

        /** @var bool $found */
        $found = $db->exists(
            'SELECT count(id) FROM ' . Chronicle::getTableName('clients') . ' WHERE publicid = ? AND publickey = ?',
            $post['clientid'],
            $post['publickey']
        );
        if (!$found) {
            return Chronicle::errorResponse(
                $response,
                'Error: Client not found. It may have already been deleted.',
                404
            );
        }
        /** @var bool $isAdmin */
        $isAdmin = $db->cell(
            'SELECT isAdmin FROM ' . Chronicle::getTableName('clients') . ' WHERE publicid = ? AND publickey = ?',
            $post['clientid'],
            $post['publickey']
        );
        if ($isAdmin) {
            return Chronicle::errorResponse(
                $response,
                'You cannot delete administrators from this API.',
                403
            );
        }

        $db->delete(
            Chronicle::getTableName('clients', true),
            [
                'publicid' => $post['clientid'],
                'publickey' => $post['publickey'],
                'isAdmin' => false
            ]
        );
        if ($db->commit()) {
            // Confirm deletion:
            $result = [
                'deleted' => !$db->exists(
                    'SELECT count(id) FROM ' .
                    Chronicle::getTableName('clients') .
                    ' WHERE publicid = ? AND publickey = ?',
                    $post['clientid'],
                    $post['publickey']
                )
            ];

            if (!$result['deleted']) {
                $result['reason'] = 'Delete operation was unsuccessful due to unknown reasons.';
            }
            try {
                $now = (new \DateTime())->format(\DateTime::ATOM);
            } catch (\Exception $ex) {
                return Chronicle::errorResponse($response, $ex->getMessage(), 500);
            }

            $settings = Chronicle::getSettings();
            if (!empty($settings['publish-revoked-clients'])) {
                $serverKey = Chronicle::getSigningKey();
                $message = \json_encode(
                    [
                        'server-action' => 'Client Access Revocation',
                        'now' => $now,
                        'clientid' => $post['clientid'],
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
            /** @var array<int, string> $errorInfo */
            $errorInfo = $db->errorInfo();
            return Chronicle::errorResponse(
                $response,
                $errorInfo[0],
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
