<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Process;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use ParagonIE\Blakechain\Blakechain;
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\{
    ReplicationSourceNotFound,
    SecurityViolation
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Adapter\Guzzle;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\Sapient;

/**
 * Class Replicate
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
    public function __construct(int $id, string $name, string $url, SigningPublicKey $publicKey)
    {
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
        $this->publicKey = $publicKey;

        $this->now = new \DateTime();
        $this->guzzle = new Client();
        $this->sapient = new Sapient(new Guzzle($this->guzzle));
    }

    /**
     * @param int $id
     * @return self
     * @throws ReplicationSourceNotFound
     */
    public static function byId(int $id): self
    {
        $row = Chronicle::getDatabase()->row(
            "SELECT * FROM chronicle_replication_sources WHERE id = ?",
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
     */
    public function replicate()
    {
        $response = $this->getUpstream($this->getLatestSummaryHash());
        foreach ($response['results'] as $row) {
            try {
                $this->appendToChain($row);
            } catch (\Throwable $ex) {
                continue;
            }
        }
    }

    /**
     * Add an entry to the Blakechain for this replica of the upstream
     * Chronicle.
     *
     * @param array $entry
     * @return bool
     * @throws SecurityViolation
     */
    protected function appendToChain(array $entry): bool
    {
        $db = Chronicle::getDatabase();
        $db->beginTransaction();
        $lasthash = $db->row(
            'SELECT currhash, hashstate FROM chronicle_replication_chain WHERE source = ? ORDER BY id DESC LIMIT 1',
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
            throw new SecurityViolation('Invalid Ed25519 signature');
        }

        /* Update the Blakechain */
        $blakechain->appendData(
            $entry['created'] .
            $decodedPk .
            $decodedSig .
            $entry['contents']
        );

        /* If the summary hash we calculated doesn't match what was given, abort */
        if (!\hash_equals($entry['summary'], $blakechain->getSummaryHash())) {
            $db->rollBack();
            throw new SecurityViolation(
                'Invalid summary hash. Expected ' . $entry['summary'] .
                ', calculated ' . $blakechain->getSummaryHash()
            );
        }

        /* Enter the new row to the replication table */
        $db->insert('chronicle_replication_chain', [
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
     */
    protected function getLatestSummaryHash(): string
    {
        $last = Chronicle::getDatabase()->cell(
            "SELECT summaryhash FROM chronicle_replication_chain WHERE source = ? ORDER BY id DESC LIMIT 1",
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
     */
    protected function getUpstream(string $lastHash = ''): array
    {
        if ($lastHash) {
            $request = new Request(
                'GET',
                $this->url . '/lookup/since/' . \urlencode($lastHash)
            );
        } else {
            $request = new Request(
                'GET',
                $this->url . '/lookup/export'
            );
        }
        return $this->sapient->decodeSignedJsonResponse(
            $this->guzzle->send($request),
            $this->publicKey
        );
    }
}
