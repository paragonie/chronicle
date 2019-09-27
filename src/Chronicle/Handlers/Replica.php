<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\FilesystemException,
    Exception\InvalidInstanceException,
    Exception\ReplicationSourceNotFound,
    Exception\HashNotFound,
    HandlerInterface,
    Pagination
};
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Replica
 * @package ParagonIE\Chronicle\Handlers
 */
class Replica implements HandlerInterface
{
    use Pagination;
    const NOTICE = 'This is replicated data from another Chronicle.';

    /** @var string */
    protected $method = 'index';

    /** @var int */
    protected $source = 0;

    /**
     * Replica constructor.
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
     * @throws \Exception
     * @throws FilesystemException
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        if (!empty($args['source'])) {
            try {
                $this->selectReplication((string) $args['source']);
            } catch (ReplicationSourceNotFound $ex) {
                return Chronicle::errorResponse($response, 'Unknown URI', 404);
            }
        } elseif ($this->method === 'index') {
            return $this->getIndex();
        } else {
            return Chronicle::errorResponse($response, 'No replication source given', 404);
        }

        try {
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
                case 'subindex':
                    return $this->getSubIndex((string) $args['source']);
                case 'since':
                    if (!empty($args['hash'])) {
                        return $this->getSince($args);
                    }
                    break;
            }
        } catch (\Throwable $ex) {
            return Chronicle::errorResponse(
                $response,
                $ex->getMessage(),
                404
            );
        }
        return Chronicle::errorResponse($response, 'Unknown URI', 404);
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
                     " . Chronicle::getTableName('replication_chain') . "
                 WHERE source = ?",
                $this->source
            );
            /** @var int $offset */
            $offset = (int) $this->getOffset((string) ($args['page'] ?? ''));
            /** @var int $limit */
            $limit = Chronicle::getPageSize();

            $page = (int) ($args['page'] ?? 1);
            if ($page > 1) {
                $response['prev'] = '/replica/' . (string) ($args['source']) . '/export/' . ($page - 1);
            }
            if ($offset + $limit <= $total) {
                if ($page < 1) {
                    $page = 1;
                }
                $response['next'] = '/replica/' . (string) ($args['source']) . '/export/' . ($page + 1);
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
     * Gets the entire Blakechain.
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     * @throws FilesystemException
     */
    public function exportChainLegacy(): ResponseInterface
    {
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'notice' => static::NOTICE,
                'results' => $this->getFullChain()
            ],
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
     * @throws HashNotFound
     * @throws FilesystemException
     * @throws InvalidInstanceException
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
                 " . Chronicle::getTableName('replication_chain') . "
             WHERE
                 source = ? AND (
                     currhash = ?
                     OR summaryhash = ?
                 )
            ",
            $this->source,
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
                'notice' => static::NOTICE,
                'results' => $record
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * List the latest current hash and summary hash for this replica
     *
     * @return ResponseInterface
     *
     * @throws FilesystemException
     * @throws InvalidInstanceException
     */
    public function getLastHash(): ResponseInterface
    {
        /** @var array<string, string> $lasthash */
        $lasthash = Chronicle::getDatabase()->row(
            'SELECT
                 currhash,
                 summaryhash
             FROM
                 ' . Chronicle::getTableName('replication_chain') . '
             WHERE
                 source = ?
             ORDER BY
                 id
             DESC LIMIT 1',
            $this->source
        );
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'notice' => static::NOTICE,
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
     * List all replicated Chronicles and their respective URIs
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     * @throws FilesystemException
     * @throws InvalidInstanceException
     */
    protected function getIndex(): ResponseInterface
    {
        /** @var array<int, array<string, string>> $replicationSources */
        $replicationSources = Chronicle::getDatabase()->run(
            "SELECT
                uniqueid,
                url AS canonical,
                name,
                publickey AS serverPublicKey
             FROM
                " . Chronicle::getTableName('replication_sources')
        );
        /**
         * @var int $idx
         * @var array<string, string> $row
         */
        foreach ($replicationSources as $idx => $row) {
            $replicationSources[$idx]['index'] = '/replica/' . $row['uniqueid'];
            $replicationSources[$idx]['urls'] = [
                [
                    'uri' => '/replica/' . $row['uniqueid'] . '/lasthash',
                    'description' => 'Get information about the latest entry in this replicated Chronicle'
                ], [
                    'uri' => '/replica/' . $row['uniqueid'] . '/lookup/{hash}',
                    'description' => 'Lookup the information for the given hash in this replicated Chronicle'
                ], [
                    'uri' => '/replica/' . $row['uniqueid'] . '/since/{hash}',
                    'description' => 'List all new entries since a given hash in this replicated Chronicle'
                ], [
                    'uri' => '/replica/' . $row['uniqueid'] . '/export',
                    'description' => 'Export the entire replicated Chronicle'
                ]
            ];
        }
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $replicationSources
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Sub-index of the /replica/{identifier} request
     *
     * @param string $replica
     * @return ResponseInterface
     * @throws FilesystemException
     * @throws InvalidInstanceException
     */
    protected function getSubIndex(string $replica): ResponseInterface
    {
        /** @var array<string, string> $source */
        $source = Chronicle::getDatabase()->row(
            "SELECT 
                uniqueid,
                url AS canonical,
                name,
                publickey AS serverPublicKey
            FROM " . Chronicle::getTableName('replication_sources') . " WHERE uniqueid = ?",
            $replica
        );

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => [
                    'uniqueid' => $source['uniqueid'],
                    'serverPublicKey' => $source['serverPublicKey'],
                    'canonical' => $source['canonical'],
                    'urls' => [
                        [
                            'uri' => '/replica/' . $replica . '/lasthash',
                            'description' => 'Get information about the latest entry in this replicated Chronicle'
                        ], [
                            'uri' => '/replica/' . $replica . '/lookup/{hash}',
                            'description' => 'Lookup the information for the given hash in this replicated Chronicle'
                        ], [
                            'uri' => '/replica/' . $replica . '/since/{hash}',
                            'description' => 'List all new entries since a given hash in this replicated Chronicle'
                        ], [
                            'uri' => '/replica/' . $replica. '/export',
                            'description' => 'Export the entire replicated Chronicle'
                        ]
                    ]
                ]
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Get updates to the replica since a given hash
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
                 " . Chronicle::getTableName('replication_chain') . "
             WHERE
                 source = ? AND (
                     currhash = ?
                     OR summaryhash = ?
                 )
             ORDER BY id ASC
            ",
            $this->source,
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
             " . Chronicle::getTableName('replication_chain') . "
         WHERE
             source = ? AND id > ?";

        // Append an offset and limit to the query string if applicable
        if ($paginated) {
            $total = (int) Chronicle::getDatabase()->cell(
                "SELECT 
                    count(id)
                 FROM
                     " . Chronicle::getTableName('replication_chain') . "
                 WHERE
                    source = ? AND id > ?",
                $this->source,
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
        $since = Chronicle::getDatabase()->run($sinceQuery, $this->source, $id);
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
                $response['prev'] = '/replica/' . (string) ($args['source']) .
                    '/since/' . (string)($args['hash']) .
                    '/' . ($page - 1);
            }
            if ($offset + $limit <= $total) {
                if ($page < 1) {
                    $page = 1;
                }
                $response['next'] = '/replica/' . (string) ($args['source']) .
                    '/since/' . (string)($args['hash']) .
                    '/' . ($page + 1);
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
            "SELECT * FROM " . Chronicle::getTableName('replication_chain') . " WHERE source = ? ORDER BY id ASC" .
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
            "SELECT * FROM " . Chronicle::getTableName('replication_chain') . " WHERE source = ? ORDER BY id ASC"
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
        $rows = Chronicle::getDatabase()->run($queryString, $this->source);
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

    /**
     * Given a unique ID, set this object's source property to the respective
     * database record ID (to use in future querying).
     *
     * @param string $uniqueId
     * @return self
     *
     * @throws ReplicationSourceNotFound
     * @throws InvalidInstanceException
     */
    protected function selectReplication(string $uniqueId): self
    {
        /** @var int $source */
        $source = Chronicle::getDatabase()->cell(
            "SELECT id FROM " . Chronicle::getTableName('replication_sources') . " WHERE uniqueid = ?",
            $uniqueId
        );
        if (!$source) {
            throw new ReplicationSourceNotFound();
        }
        $this->source = (int) $source;
        return $this;
    }
}
