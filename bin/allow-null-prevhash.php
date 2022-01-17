<?php
declare(strict_types=1);

/**
 * This script sets prevhash of the genesis block to be NULL instead
 * of empty string, then adds the foreign key / unique constraints
 * (if the database driver allows it).
 */
use ParagonIE\EasyDB\{
    EasyDB,
    Factory
};

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

if (!\is_readable($root . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

/** @var array<string, string> $settings */
$settings = \json_decode(
    (string) \file_get_contents($root . '/local/settings.json'),
    true
);
$db = Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);
$db->update(
    'chronicle_chain',
    ['prevhash' => null],
    ['prevhash' => '']
);
$db->update(
    'chronicle_replication_chain',
    ['prevhash' => null],
    ['prevhash' => '']
);

if ($db->getDriver() !== 'sqlite') {
    $db->exec(
        "ALTER TABLE chronicle_chain
            ADD CONSTRAINT chronicle_chain_prevhash_currhash_fk
            FOREIGN KEY (prevhash)
            REFERENCES chronicle_chain(currhash)
         ON DELETE RESTRICT;"
    );
    $db->exec(
        "ALTER TABLE chronicle_chain
            ADD CONSTRAINT chronicle_chain_prevhash_unique
            UNIQUE (prevhash)
         ON DELETE RESTRICT;"
    );
    $db->exec(
        "ALTER TABLE chronicle_replication_chain
            ADD CONSTRAINT chronicle_replication_chain_prevhash_currhash_fk
            FOREIGN KEY (prevhash)
            REFERENCES chronicle_replication_chain(currhash)
         ON DELETE RESTRICT;"
    );
    $db->exec(
        "ALTER TABLE chronicle_replication_chain
            ADD CONSTRAINT chronicle_replication_chain_prevhash_unique
            UNIQUE (source, prevhash)
         ON DELETE RESTRICT;"
    );
}
