<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Process;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\GuzzleException;
use ParagonIE\Blakechain\Blakechain;
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\{InvalidInstanceException, ReplicationSourceNotFound, SecurityViolation};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Adapter\Guzzle;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\Exception\InvalidMessageException;
use ParagonIE\Sapient\Sapient;

/**
 * Class Replicate
 *
 * Maintain a replica (mirror) of another Chronicle instance.
 * Unless Attestation is enabled, this doesn't affect the main
 * Chronicle; mirroring is separate.
 *
 * @package ParagonIE\Chronicle\Process
 */
class Replicate
{
    /** @var Client */
    protected $guzzle;

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var \DateTime */
    protected $now;

    /** @var SigningPublicKey */
    protected $publicKey;

    /** @var string */
    protected $url;

    /** @var Sapient */
    protected $sapient;

    /**
     * Replicate constructor.
     *
     * @param int $id
     * @param string $name
     * @param string $url
     * @param SigningPublicKey $publicKey
     */
    public function __construct(
        int $id,
        string $name,
        string $url,
        SigningPublicKey $publicKey
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
        $this->publicKey = $publicKey;

        $this->now = new \DateTime();
        $this->guzzle = new Client();
        $this->sapient = new Sapient(new Guzzle($this->guzzle));
    }

    /**
     * Get a Replica instance, given its database ID
     *
     * @param int $id
     * @return self
     *
     * @throws ReplicationSourceNotFound
     */
    public static function byId(int $id): self
    {
        /** @var array<string, string> $row */
        $row = Chronicle::getDatabase()->row(
            "SELECT * FROM " . Chronicle::getTableName('replication_sources') . " WHERE id = ?",
            $id
        );
        if (empty($row)) {
            throw new ReplicationSourceNotFound(
                'Could not find a replication source for this ID'
            );
        }
        return new static(
            (int) $row['id'],
            $row['name'],
            $row['url'],
            new SigningPublicKey(Base64UrlSafe::decode($row['publickey']))
        );
    }

    /**
     * Append new data to the replication table.
     *
     * @return void
     *
     * @throws GuzzleException
     * @throws InvalidMessageException
     * @throws SecurityViolation
     * @throws \SodiumException
     */
    public function replicate()
    {
        $response = $this->getUpstream($this->getLatestSummaryHash());
        /** @var array<string, string> $row */
        foreach ($response['results'] as $row) {
            $this->appendToChain($row);
        }
    }

    /**
     * Add an entry to the Blakechain for this replica of the upstream
     * Chronicle.
     *
     * @param array<string, string> $entry
     * @return bool
     *
     * @throws SecurityViolation
     * @throws InvalidInstanceException
     * @throws \SodiumException
     */
    protected function appendToChain(array $entry): bool
    {
        $db = Chronicle::getDatabase();
        $db->beginTransaction();
        /** @var array<string, string> $lasthash */
        $lasthash = $db->row(
            'SELECT
                 currhash,
                 hashstate
             FROM
                 ' . Chronicle::getTableName('replication_chain') . '
             WHERE
                 source = ?
             ORDER BY id DESC
             LIMIT 1',
            $this->id
        );

        $blakechain = new Blakechain();
        if (empty($lasthash)) {
            $prevhash = '';
        } else {
            $prevhash = $lasthash['currhash'];
            $blakechain->setFirstPrevHash(
                Base64UrlSafe::decode($lasthash['currhash'])
            );
            $hashstate = Base64UrlSafe::decode($lasthash['hashstate']);
            $blakechain->setSummaryHashState($hashstate);
        }
        $decodedSig = Base64UrlSafe::decode($entry['signature']);
        $decodedPk = Base64UrlSafe::decode($entry['publickey']);

        /* If the signature is not valid for this public key, abort: */
        $sigMatches = \ParagonIE_Sodium_Compat::crypto_sign_verify_detached(
            $decodedSig,
            $entry['contents'],
            $decodedPk
        );
        if (!$sigMatches) {
            $db->rollBack();
            throw new SecurityViolation('Invalid Ed25519 signature provided by source Chronicle.');
        }
        if (!isset($entry['summaryhash'])) {
            if (!isset($entry['summary'])) {
                $db->rollBack();
                throw new SecurityViolation('No summary hash provided');
            }
            $entry['summaryhash'] =& $entry['summary'];
        }

        /* Update the Blakechain */
        $blakechain->appendData(
            $entry['created'] .
            $decodedPk .
            $decodedSig .
            $entry['contents']
        );

        /* If the summary hash we calculated doesn't match what was given, abort */
        if (!\hash_equals($entry['summaryhash'], $blakechain->getSummaryHash())) {
            $db->rollBack();
            throw new SecurityViolation(
                'Invalid summary hash. Expected ' . $entry['summary'] .
                ', calculated ' . $blakechain->getSummaryHash()
            );
        }

        /* Enter the new row to the replication table */
        $db->insert(Chronicle::getTableName('replication_chain', true), [
            'source' => $this->id,
            'data' => $entry['contents'],
            'prevhash' => $prevhash,
            'currhash' => $blakechain->getLastHash(),
            'hashstate' => $blakechain->getSummaryHashState(),
            'summaryhash' => $blakechain->getSummaryHash(),
            'publickey' => $entry['publickey'],
            'signature' => $entry['signature'],
            'created' => $entry['created'],
            'replicated' => (new \DateTime())->format(\DateTime::ATOM)
        ]);
        return $db->commit();
    }

    /**
     * Get the latest summary hash from this replica.
     *
     * @return string
     * @throws InvalidInstanceException
     */
    protected function getLatestSummaryHash(): string
    {
        /** @var string $last */
        $last = Chronicle::getDatabase()->cell(
            "SELECT
                 summaryhash
             FROM
                 " . Chronicle::getTableName('replication_chain') . "
             WHERE
                 source = ?
             ORDER BY id DESC
             LIMIT 1",
            $this->id
        );
        if (empty($last)) {
            return '';
        }
        return $last;
    }

    /**
     * Get the updates from the upstream server.
     *
     * @param string $lastHash
     * @return array
     *
     * @throws GuzzleException
     * @throws InvalidMessageException
     */
    protected function getUpstream(string $lastHash = ''): array
    {
        if ($lastHash) {
            $request = new Request(
                'GET',
                $this->url . '/since/' . \urlencode($lastHash)
            );
        } else {
            $request = new Request(
                'GET',
                $this->url . '/export'
            );
        }
        return $this->sapient->decodeSignedJsonResponse(
            $this->guzzle->send($request),
            $this->publicKey
        );
    }
}
