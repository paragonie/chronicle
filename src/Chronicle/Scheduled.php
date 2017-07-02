<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use ParagonIE\Chronicle\Process\CrossSign;
use ParagonIE\Chronicle\Process\Replicate;
use ParagonIE\EasyDB\EasyDB;

/**
 * Class Scheduled
 *
 * @package ParagonIE\Chronicle
 */
class Scheduled
{
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
}
