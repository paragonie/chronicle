<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\HandlerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;

/**
 * Class Lookup
 * @package ParagonIE\Chronicle\Handlers
 */
class Lookup implements HandlerInterface
{
    /** @var string */
    protected $method = 'index';

    /**
     * Lookup constructor.
     * @param string $method
     */
    public function __construct(string $method = 'index')
    {
        $this->method = $method;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return mixed
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        try {
            switch ($this->method) {
                case 'export':
                    return $this->exportChain();
                case 'lasthash':
                    return $this->getLastHash();
                case 'lookup':
                    if (!empty($args['hash'])) {
                        return $this->getByHash($args);
                    }
                    break;
                case 'since':
                    if (!empty($args['hash'])) {
                        return $this->getSince($args);
                    }
                    break;
            }
        } catch (\Throwable $ex) {
            return Chronicle::errorResponse($response, $ex->getMessage());
        }
        return Chronicle::errorResponse($response, 'Unknown method');
    }

    /**
     * @return ResponseInterface
     */
    public function exportChain(): ResponseInterface
    {
        $chain = $this->getFullChain();
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $chain
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * @param array $args
     * @return ResponseInterface
     * @throws \Error
     */
    public function getByHash(array $args = []): ResponseInterface
    {
        $record = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash
             FROM
                 chronicle_chain
             WHERE
                 currhash = ?
                 OR summaryhash = ?
            ",
            $args['hash'],
            $args['hash']
        );
        if (!$record) {
            throw new \Error('No record found matching this hash.');
        }
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $record
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * @return ResponseInterface
     */
    public function getLastHash(): ResponseInterface
    {
        $lasthash = Chronicle::getDatabase()->row(
            'SELECT currhash, summaryhash FROM chronicle_chain ORDER BY id DESC LIMIT 1'
        );
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => [
                    'curr-hash' =>
                        $lasthash['currhash'],
                    'summary-hash' =>
                        $lasthash['summaryhash']
                ]
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * @param array $args
     * @return ResponseInterface
     * @throws \Error
     */
    public function getSince(array $args = []): ResponseInterface
    {
        $id = Chronicle::getDatabase()->cell(
            "SELECT
                 id
             FROM
                 chronicle_chain
             WHERE
                 currhash = ?
                 OR summaryhash = ?
             ORDER BY id ASC
            ",
            $args['hash'],
            $args['hash']
        );
        if (!$id) {
            throw new \Error('No record found matching this hash.');
        }
        $since = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash
             FROM
                 chronicle_chain
             WHERE
                 id > ?
            ",
            $id
        );

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $since
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * @return array
     */
    protected function getFullChain(): array
    {
        $chain = [];
        $rows = Chronicle::getDatabase()->run("SELECT * FROM chronicle_chain ORDER BY id ASC");
        foreach ($rows as $row) {
            $chain[] = [
                'contents' => $row['data'],
                'prev' => $row['prevhash'],
                'hash' => $row['currhash'],
                'summary' => $row['summaryhash']
            ];
        }
        return $chain;
    }
}
