<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\FilesystemException,
    Exception\InvalidInstanceException,
    HandlerInterface
};
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Mirrors
 * @package ParagonIE\Chronicle\Handlers
 */
class Mirrors implements HandlerInterface
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws InvalidInstanceException
     * @throws FilesystemException
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        $signingKey = Chronicle::getSigningKey();
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'public-key' => $signingKey->getPublicKey()->getString(),
                'results' => $this->getMirrors()
            ],
            $signingKey
        );
    }

    /**
     * @return array
     * @throws InvalidInstanceException
     */
    protected function getMirrors(): array
    {
        $mirrors = Chronicle::getDatabase()->run(
            "SELECT
                url, publickey, comment
            FROM 
                 " . Chronicle::getTableName('mirrors') . "
            ORDER BY sortpriority ASC"
        );
        if (!is_array($mirrors)) {
            return [];
        }
        return $mirrors;
    }
}
