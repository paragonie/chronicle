<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\FilesystemException,
    Exception\HashNotFound,
    Exception\InvalidInstanceException,
    HandlerInterface,
    Pagination
};
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Lookup
 * @package ParagonIE\Chronicle\Handlers
 */
class Lookup implements HandlerInterface
{
    use Pagination;

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
     * The handler gets invoked by the router. This accepts a Request
     * and returns a Response.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     *
     * @throws FilesystemException
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        try {
            // Whitelist of acceptable methods:
            switch ($this->method) {
                case 'export':
                    return $this->exportChain($args);
                case 'lasthash':
                    return $this->getLastHash();
                case 'hash':
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
        return Chronicle::errorResponse($response, 'Unknown method: '.$this->method);
    }

    /**
     * Gets the entire Blakechain.
     *
     * @param array $args
     * @return ResponseInterface
     *
     * @throws \Exception
     * @return ResponseInterface
     * @throws FilesystemException
     * @throws InvalidInstanceException
     */
    public function exportChain(array $args = []): ResponseInterface
    {
        /** @var bool $paginated */
        $paginated = Chronicle::shouldPaginate();
        /** @var int $total */
        $total = 0;
        /** @var int $offset */
        $offset = 0;
        /** @var int $limit */
        $limit = 0;
        $response = [
            'version' => Chronicle::VERSION,
            'datetime' => (new \DateTime())->format(\DateTime::ATOM),
            'status' => 'OK',
        ];
        if ($paginated) {
            $response['paginated'] = true;
            $total = (int) Chronicle::getDatabase()->cell(
                "SELECT 
                    count(id)
                 FROM
                     " . Chronicle::getTableName('chain') . "
                 "
            );
            /** @var int $offset */
            $offset = (int) $this->getOffset((string) ($args['page'] ?? ''));
            /** @var int $limit */
            $limit = Chronicle::getPageSize();

            $page = (int) ($args['page'] ?? 1);
            if ($page > 1) {
                $response['prev'] = '/export/' . ($page - 1);
            }
            if ($offset + $limit <= $total) {
                if ($page < 1) {
                    $page = 1;
                }
                $response['next'] = '/export/' . ($page + 1);
            }
            $response['results'] = $this->getPartialChain($offset, $limit);
        } else {
            $fullChain = $this->getFullChain();
            $response['total'] = count($fullChain);
            $response['results'] = $fullChain;
        }
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            $response,
            Chronicle::getSigningKey()
        );
    }

    /**
     * Get information about a particular entry, given its hash.
     *
     * @param array $args
     * @return ResponseInterface
     *
     * @throws \Exception
     * @throws FilesystemException
     * @throws HashNotFound
     */
    public function getByHash(array $args = []): ResponseInterface
    {
        /** @var array<int, array<string, string>> $record */
        $record = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash,
                 created,
                 publickey,
                 signature
             FROM
                 " . Chronicle::getTableName('chain') . "
             WHERE
                 currhash = ?
                 OR summaryhash = ?
            ",
            $args['hash'],
            $args['hash']
        );
        if (!$record) {
            throw new HashNotFound('No record found matching this hash.');
        }
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $record
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * List the latest current record
     *
     * @return ResponseInterface
     *
     * @throws FilesystemException
     * @throws InvalidInstanceException
     */
    public function getLastHash(): ResponseInterface
    {
        /** @var array<string, string> $record */
        $record = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash,
                 created,
                 publickey,
                 signature
             FROM
                 " . Chronicle::getTableName('chain') . "
             ORDER BY id DESC LIMIT 1
            "
        );
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $record,
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Get updates to the chain since a given hash
     *
     * @param array $args
     * @return ResponseInterface
     *
     * @throws \Exception
     * @throws FilesystemException
     * @throws HashNotFound
     * @throws InvalidInstanceException
     */
    public function getSince(array $args = []): ResponseInterface
    {
        /** @var bool $paginated */
        $paginated = Chronicle::shouldPaginate();
        /** @var int $total */
        $total = 0;
        /** @var int $offset */
        $offset = 0;
        /** @var int $limit */
        $limit = 0;

        /** @var int $id */
        $id = Chronicle::getDatabase()->cell(
            "SELECT
                 id
             FROM
                 " . Chronicle::getTableName('chain') . "
             WHERE
                 currhash = ?
                 OR summaryhash = ?
             ORDER BY id ASC
            ",
            $args['hash'],
            $args['hash']
        );
        if (!$id) {
            throw new HashNotFound('No record found matching this hash.');
        }
        /** @var string $sinceQuery */
        $sinceQuery = "SELECT
             data AS contents,
             prevhash,
             currhash,
             summaryhash,
             created,
             publickey,
             signature
         FROM
             " . Chronicle::getTableName('chain') . "
         WHERE
             id > ?";

        // Append an offset and limit to the query string if applicable
        if ($paginated) {
            $total = (int) Chronicle::getDatabase()->cell(
                "SELECT 
                    count(id)
                 FROM
                     " . Chronicle::getTableName('chain') . "
                 WHERE
                    id > ?",
                $id
            );
            /** @var int $offset */
            $offset = (int) $this->getOffset((string) ($args['page'] ?? ''));
            /** @var int $limit */
            $limit = Chronicle::getPageSize();
            $sinceQuery .= $this->formatOffsetSuffix($offset, $limit);
        }

        // Fetch the results
        /** @var array<int, array<string, string>> $since */
        $since = Chronicle::getDatabase()->run($sinceQuery, $id);
        if (!$total) {
            $total = count($since);
        }

        // Process the response
        $response = [
            'version' => Chronicle::VERSION,
            'datetime' => (new \DateTime())->format(\DateTime::ATOM),
            'status' => 'OK'
        ];

        // Add total and optional 'next' URL
        if ($paginated) {
            $response['paginated'] = true;
            $page = (int) ($args['page'] ?? 1);
            if ($page > 1) {
                $response['prev'] = '/since/' . (string)($args['hash']) . '/' . ($page - 1);
            }
            if ($offset + $limit <= $total) {
                if ($page < 1) {
                    $page = 1;
                }
                $response['next'] = '/since/' .  (string) ($args['hash']) . '/' . ($page + 1);
            }
            $response['total'] = $total;
        } else {
            $response['total'] = count($since);
        }
        $response['results'] = $since;

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            $response,
            Chronicle::getSigningKey()
        );
    }

    /**
     * Get a subset of the total chain.
     *
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws InvalidInstanceException
     */
    protected function getPartialChain(int $offset, int $limit): array
    {
        return $this->getChain(
            "SELECT * FROM " . Chronicle::getTableName('chain') . " ORDER BY id ASC" .
            $this->formatOffsetSuffix($offset, $limit)
        );
    }

    /**
     * Get the entire chain, as-is, as of the time of the request.
     *
     * @return array
     * @throws InvalidInstanceException
     */
    protected function getFullChain(): array
    {
        return $this->getChain(
            "SELECT * FROM " . Chronicle::getTableName('chain') . " ORDER BY id ASC"
        );
    }

    /**
     * @param string $queryString
     * @return array
     */
    protected function getChain(string $queryString): array
    {
        $chain = [];
        /** @var array<int, array<string, string>> $rows */
        $rows = Chronicle::getDatabase()->run($queryString);
        /** @var array<string, string> $row */
        foreach ($rows as $row) {
            $chain[] = [
                'contents' => $row['data'],
                'prev' => $row['prevhash'],
                'hash' => $row['currhash'],
                'summary' => $row['summaryhash'],
                'created' => $row['created'],
                'publickey' => $row['publickey'],
                'signature' => $row['signature']
            ];
        }
        return $chain;
    }
}
