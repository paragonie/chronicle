<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use ParagonIE\Chronicle\Process\{
    Attest,
    CrossSign,
    Replicate
};

/**
 * Class Scheduled
 *
 * @package ParagonIE\Chronicle
 */
class Scheduled
{
    /**@var array */
    protected $settings;

    /**
     * Scheduled constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * Invoked by a CLI script, this runs all of the scheduled tasks.
     *
     * @return self
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
     */
    public function doCrossSigns(): self
    {
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
     */
    public function doReplication(): self
    {
        foreach (Chronicle::getDatabase()->run('SELECT id FROM chronicle_replication_sources') as $row) {
            Replicate::byId((int) $row['id'])->replicate();
        }
        return $this;
    }

    /**
     * @return self
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
