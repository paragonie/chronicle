<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use ParagonIE\Chronicle\Process\CrossSign;
use ParagonIE\EasyDB\EasyDB;

/**
 * Class Scheduled
 *
 * Invoked by a CLI script, this runs the scheduled tasks
 *
 * @package ParagonIE\Chronicle
 */
class Scheduled
{
    /** @var EasyDB $db */
    protected $db;

    /**
     * Cron constructor.
     * @param EasyDB $db
     */
    public function __construct(EasyDB $db)
    {
        $this->db = $db;
        Chronicle::setDatabase($db);
    }

    /**
     * @return void
     */
    public function run()
    {
        $this->doCrossSigns();
    }

    /**
     * @return void
     */
    public function doCrossSigns()
    {
        foreach ($this->db->run('SELECT id FROM chronicle_xsign_targets') as $row) {
            $xsign = CrossSign::byId((int) $row['id']);
            if ($xsign->needsToCrossSign()) {
                $xsign->performCrossSign();
            }
        }
    }
}
