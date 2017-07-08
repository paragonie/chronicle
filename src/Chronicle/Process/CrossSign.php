<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Process;

use GuzzleHttp\Client;
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\EasyDB\EasyDB;
use ParagonIE\Sapient\Adapter\Guzzle;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\Sapient;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CrossSign
 * @package ParagonIE\Chronicle\Process
 */
class CrossSign
{
    /** @var Client */
    protected $guzzle;

    /** @var int */
    protected $id;

    /** @var array */
    protected $lastRun;

    /** @var string */
    protected $name;

    /** @var \DateTime */
    protected $now;

    /** @var array */
    protected $policy;

    /** @var SigningPublicKey */
    protected $publicKey;

    /** @var Sapient */
    protected $sapient;

    /** @var string */
    protected $url;

    /**
     * CrossSign constructor.
     * @param int $id
     * @param string $name
     * @param string $url
     * @param SigningPublicKey $publicKey
     * @param array $policy
     * @param array $lastRun
     */
    public function __construct(
        int $id,
        string $name,
        string $url,
        SigningPublicKey $publicKey,
        array $policy,
        array $lastRun = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
        $this->publicKey = $publicKey;
        $this->policy = $policy;
        $this->lastRun = $lastRun;
        $this->now = new \DateTime();
        $this->guzzle = new Client();
        $this->sapient = new Sapient(new Guzzle($this->guzzle));
    }

    /**
     * Get a CrossSign instance, given its database ID
     *
     * @param int $id
     * @return self
     * @throws \Error
     */
    public static function byId(int $id): self
    {
        $db = Chronicle::getDatabase();
        $data = $db->row('SELECT * FROM chronicle_xsign_targets WHERE id = ?', $id);
        if (empty($data)) {
            throw new \Error('Cross-sign target not found');
        }
        $policy = \json_decode($data['policy'], true);
        $lastRun = \json_decode($data['lastrun'], true);

        return new static(
            $id,
            $data['name'],
            $data['url'],
            new SigningPublicKey(Base64UrlSafe::decode($data['publickey'])),
            \is_array($policy) ? $policy : [],
            \is_array($lastRun) ? $lastRun : []
        );
    }

    /**
     * Are we supposed to cross-sign our latest hash to this target?
     *
     * @return bool
     * @throws \Error
     */
    public function needsToCrossSign(): bool
    {
        if (empty($this->lastRun)) {
            return true;
        }
        if (!isset($this->lastRun['time'], $this->lastRun['id'])) {
            return true;
        }
        $db = Chronicle::getDatabase();

        if (isset($this->policy['push-after'])) {
            $head = $db->cell('SELECT MAX(id) FROM chronicle_chain');
            // Only run if we've had more than N entries
            if (($head - $this->lastRun['id']) >= $this->policy['push-after']) {
                return true;
            }
            // Otherwise, fall back to the daily scheduler:
        }

        if (isset($this->policy['push-days'])) {
            $days = (string) \intval($this->policy['push-days']);
            if ($days < 10) {
                $days = '0' . $days;
            }
            $lastRun = (new \DateTime($this->lastRun['time']))
                ->add(new \DateInterval('P' . $days . 'D'));

            // Return true only if we're more than N days since the last run:
            return new $this->now > $lastRun;
        }

        throw new \Error('No valid policy configured');
    }

    /**
     * Perform the actual cross-signing.
     *
     * First, sign and send a JSON request to the server.
     * Then, verify and decode the JSON response.
     * Finally, update the local metadata table.
     *
     * @return bool
     */
    public function performCrossSign(): bool
    {
        $db = Chronicle::getDatabase();
        $message = $this->getEndOfChain($db);
        $response = $this->sapient->decodeSignedJsonResponse(
            $this->sendToPeer($message),
            $this->publicKey
        );
        return $this->updateLastRun($db, $response, $message);
    }

    /**
     * Send a signed request to our peer, return their response.
     *
     * @param array $message
     * @return ResponseInterface
     */
    protected function sendToPeer(array $message): ResponseInterface
    {
        $signingKey = Chronicle::getSigningKey();
        return $this->guzzle->send(
            $this->sapient->createSignedJsonRequest(
                'POST',
                $this->url . '/publish',
                [
                    'target' => $this->publicKey->getString(),
                    'cross-sign-at' => $this->now->format(\DateTime::ATOM),
                    'currhash' => $message['currhash'],
                    'summaryhash' => $message['summaryhash']
                ],
                $signingKey
            )
        );
    }

    /**
     * Get the last row in this Chronicle's chain.
     *
     * @param EasyDB $db
     * @return array
     */
    protected function getEndOfChain(EasyDB $db): array
    {
        return $db->row('SELECT * FROM chronicle_chain ORDER BY id DESC LIMIT 1');
    }

    /**
     * Update the lastrun element of the cross-signing table, which helps
     * enforce our local cross-signing policies:
     *
     * @param EasyDB $db
     * @param array $response
     * @param array $message
     * @return bool
     */
    protected function updateLastRun(EasyDB $db, array $response, array $message): bool
    {
        $db->beginTransaction();
        $db->update(
            'chronicle_xsign_targets',
            [
                'lastrun' => \json_encode([
                    'id' => $message['id'],
                    'time' => $this->now->format(\DateTime::ATOM),
                    'response' => $response
                ])
            ],
            ['id' => $this->id]
        );
        return $db->commit();
    }
}
