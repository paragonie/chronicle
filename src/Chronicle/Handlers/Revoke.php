<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    HandlerInterface
};
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
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     *
     * @throws \Error
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
                throw new \Error('Unauthenticated request');
            }
            if (!$request->getAttribute('administrator')) {
                throw new \Error('Unprivileged request');
            }
        } else {
            throw new \TypeError('Something unexpected happen when attempting to publish.');
        }

        // Get the parsed POST body:
        $post = $request->getParsedBody();
        if (!\is_array($post)) {
            throw new \Error('Empty post body');
        }
        if (empty($post['clientid'])) {
            throw new \Error('Error: Cliend ID expected');
        }
        if (empty($post['publickey'])) {
            throw new \Error('Error: Public key expected');
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
        } else {
            $db->rollBack();
            throw new \Error($db->errorInfo()[0]);
        }

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
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
