<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use GuzzleHttp\Exception\GuzzleException;
use ParagonIE\Chronicle\Process\{
    Attest,
    CrossSign,
    Replicate
};
use ParagonIE\Sapient\Exception\InvalidMessageException;

/**
 * Class Scheduled
 *
 * @package ParagonIE\Chronicle
 */
class Scheduled
{
    /** @var array<string, string> */
    protected $settings;

    /**
     * Scheduled constructor.
     * @param array<string, string> $settings
     */
    public function __construct(array $settings = [])
    {
        if (empty($settings)) {
            $this->settings = Chronicle::getSettings();
        }
        $this->settings = $settings;
    }

    /**
     * Invoked by a CLI script, this runs all of the scheduled tasks.
     *
     * @return self
     *
     * @throws Exception\FilesystemException
     * @throws Exception\ReplicationSourceNotFound
     * @throws Exception\SecurityViolation
     * @throws Exception\TargetNotFound
     * @throws GuzzleException
     * @throws InvalidMessageException
     * @throws \SodiumException
     */
    public function run(): self
    {
        return $this
            ->doCrossSigns()
            ->doReplication()
            ->doAttestation()
        ;
    }

    /**
     * Cross-sign to other Chronicles (where it is needed)
     *
     * @return self
     * @throws Exception\FilesystemException
     * @throws Exception\TargetNotFound
     * @throws GuzzleException
     * @throws InvalidMessageException
     */
    public function doCrossSigns(): self
    {
        /** @var array<string, int> $row */
        foreach (Chronicle::getDatabase()->run('SELECT id FROM chronicle_xsign_targets') as $row) {
            $xsign = CrossSign::byId((int) $row['id']);
            if ($xsign->needsToCrossSign()) {
                $xsign->performCrossSign();
            }
        }
        return $this;
    }

    /**
     * Replicate any new records from the Chronicles we're mirroring
     *
     * @return self
     *
     * @throws Exception\ReplicationSourceNotFound
     * @throws Exception\SecurityViolation
     * @throws GuzzleException
     * @throws InvalidMessageException
     * @throws \SodiumException
     */
    public function doReplication(): self
    {
        /** @var array<string, int> $row */
        foreach (Chronicle::getDatabase()->run('SELECT id FROM chronicle_replication_sources') as $row) {
            Replicate::byId((int) $row['id'])->replicate();
        }
        return $this;
    }

    /**
     * @return self
     *
     * @throws Exception\FilesystemException
     */
    public function doAttestation(): self
    {
        $attest = new Attest($this->settings);
        if ($attest->isScheduled()) {
            $attest->run();
        }
        return $this;
    }
}
