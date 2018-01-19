<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\FilesystemException,
    Exception\ReplicationSourceNotFound,
    Exception\HashNotFound,
    HandlerInterface
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
     * @return mixed
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        if (!empty($args['source'])) {
            try {
                $this->selectReplication($args['source']);
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
                    return $this->exportChain();
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
     * @return ResponseInterface
     * @throws FilesystemException
     */
    public function exportChain(): ResponseInterface
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
     * @throws HashNotFound
     * @throws FilesystemException
     */
    public function getByHash(array $args = []): ResponseInterface
    {
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
                 chronicle_replication_chain
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
     * @throws FilesystemException
     */
    public function getLastHash(): ResponseInterface
    {
        $lasthash = Chronicle::getDatabase()->row(
            'SELECT
                 currhash,
                 summaryhash
             FROM
                 chronicle_replication_chain
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
     * @throws FilesystemException
     */
    protected function getIndex(): ResponseInterface
    {
        $replicationSources = Chronicle::getDatabase()->run(
            "SELECT
                uniqueid,
                url AS canonical,
                name,
                publickey AS serverPublicKey
             FROM
                chronicle_replication_sources"
        );
        foreach ($replicationSources as $idx => $row) {
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
     * Get updates to the replica since a given hash
     *
     * @param array $args
     * @return ResponseInterface
     * @throws FilesystemException
     * @throws HashNotFound
     */
    public function getSince(array $args = []): ResponseInterface
    {
        $id = Chronicle::getDatabase()->cell(
            "SELECT
                 id
             FROM
                 chronicle_replication_chain
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
        $since = Chronicle::getDatabase()->run(
            "SELECT
                 data AS contents,
                 prevhash,
                 currhash,
                 summaryhash,
                 created,
                 publickey,
                 signature
             FROM
                 chronicle_replication_chain
             WHERE
                 source = ? AND
                 id > ?
            ",
            $this->source,
            $id
        );

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'notice' => static::NOTICE,
                'results' => $since
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * Export an entire replicated chain, as-is.
     *
     * @return array
     */
    protected function getFullChain(): array
    {
        $chain = [];
        $rows = Chronicle::getDatabase()->run(
            "SELECT * FROM chronicle_replication_chain WHERE source = ? ORDER BY id ASC",
            $this->source
        );
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
     * @throws ReplicationSourceNotFound
     */
    protected function selectReplication(string $uniqueId): self
    {
        $source = Chronicle::getDatabase()->cell(
            "SELECT id FROM chronicle_replication_sources WHERE uniqueid = ?",
            $uniqueId
        );
        if (!$source) {
            throw new ReplicationSourceNotFound();
        }
        $this->source = (int) $source;
        return $this;
    }
}
