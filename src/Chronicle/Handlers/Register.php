<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\HandlerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;

/**
 * Class Register
 * @package ParagonIE\Chronicle\Handlers
 */
class Register implements HandlerInterface
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws \Error
     * @throws \TypeError
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
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
        $post = $request->getParsedBody();
        if (empty($post['publickey'])) {
            throw new \Error('Error: Public key expected');
        }

        // If this is not a valid public key, let the exception be uncaught:
        $publicKeyObj = new SigningPublicKey(
            Base64UrlSafe::decode($post['publickey'])
        );

        $clientId = $this->createClient($post);

        $result = [
            'client-id' => $clientId
        ];

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

    /**
     * @param array $post
     * @return string
     * @throws \Error
     */
    protected function createClient(array $post): string
    {
        $db = Chronicle::getDatabase();
        $now = (new \DateTime())->format(\DateTime::ATOM);

        do {
            $clientId = Base64UrlSafe::encode(\random_bytes(24));
        } while ($db->exists('SELECT count(id) FROM chronicle_clients WHERE publicid = ?', $clientId));

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
            throw new \Error($db->errorInfo()[0]);
        }
        return $clientId;
    }
}
