<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Process;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * Class Attest
 * @package ParagonIE\Chronicle\Process
 */
class Attest
{
    /** @var array */
    protected $settings;

    /**
     * Attest constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * @return bool
     * @throws \Error
     */
    public function isScheduled(): bool
    {
        if (!Chronicle::getDatabase()->exists('SELECT count(id) FROM chronicle_replication_sources')) {
            return false;
        }
        if (!isset($this->settings['scheduled-attestation'])) {
            return false;
        }
        if (!\file_exists(CHRONICLE_APP_ROOT . '/local/replication-last-run')) {
            return true;
        }
        $lastRun = \file_get_contents(CHRONICLE_APP_ROOT . '/local/replication-last-run');
        if (!\is_string($lastRun)) {
            throw new \Error('Could not read replication last run file');
        }

        $now = new \DateTimeImmutable('NOW');
        $runTime = new \DateTimeImmutable($lastRun);

        // Return true only if the next scheduled run has come to pass.
        $interval = \DateInterval::createFromDateString($this->settings['scheduled-attestation']);
        $nextRunTime = $runTime->add($interval);
        return $nextRunTime < $now;
    }

    /**
     * @throws \Error
     * @return void
     */
    public function run()
    {
        $now = (new \DateTime('NOW'))->format(\DateTime::ATOM);

        /** @var int|bool $lock */
        $lock = \file_put_contents(
            CHRONICLE_APP_ROOT . '/local/replication-last-run',
            $now
        );
        if (!\is_int($lock)) {
            throw new \Error('Cannot save replication last run file.');
        }
        $this->attestAll();
    }

    /**
     * @return array
     */
    public function attestAll(): array
    {
        $hashes = [];
        foreach (Chronicle::getDatabase()->run('SELECT id, uniqueid FROM chronicle_replication_sources') as $row) {
            $latest = Chronicle::getDatabase()->row(
                "SELECT currhash, summaryash FROM chronicle_replication_chain WHERE source = ? ORDER BY id DESC LIMIT 1",
                $row['id']
            );
            $latest['source'] = $row['uniqueid'];
            $hashes[] = $latest;
        }

        // Build the message
        $message = \json_encode(
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'replication-hashes' => $hashes
            ],
            JSON_PRETTY_PRINT
        );

        // Sign the message:
        $signature = Base64UrlSafe::encode(
            \ParagonIE_Sodium_Compat::crypto_sign_detached(
                $message,
                Chronicle::getSigningKey()->getString(true)
            )
        );

        // Write the message onto the local Blakechain
        return Chronicle::extendBlakechain(
            $message,
            $signature,
            Chronicle::getSigningKey()->getPublicKey()
        );
    }
}
