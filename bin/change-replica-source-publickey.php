<?php

declare(strict_types=1);

/**
 * This script sets up replication of another Chronicle
 */
use GetOpt\{
    GetOpt,
    Option
};
use ParagonIE\EasyDB\{
    EasyDB,
    Factory
};
use GuzzleHttp\Client;
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\InstanceNotFoundException;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;

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
/** @var EasyDB $db */
$db = Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);
Chronicle::setDatabase($db);

/**
 * @var Getopt
 *
 * This defines the Command Line options.
 */
$getopt = new GetOpt([
    new Option(null, 'id', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option('i', 'instance', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->process();

/** @var string $id */
$id = $getopt->getOption('id');
/** @var string $publicKey */
$publicKey = $getopt->getOption('publickey');
/** @var string $instance */
$instance = $getopt->getOption('instance') ?? '';

try {
    if (!empty($instance)) {
        if (!\array_key_exists($instance, $settings['instances'])) {
            throw new InstanceNotFoundException(
                'Instance ' . $instance . ' not found'
            );
        }
        Chronicle::setTablePrefix($settings['instances'][$instance]);
    }
} catch (InstanceNotFoundException $ex) {
    echo $ex->getMessage(), PHP_EOL;
    exit(1);
}

if (!isset($id, $publicKey)) {
    echo "Not enough data. Please specify:\n",
    "\t--id\n",
    "\t--publickey\n",
    exit(1);
}

if (!$db->exists(
    'SELECT count(*) FROM ' .
    Chronicle::getTableName('replication_sources') .
    ' WHERE uniqueid = ?', $id)) {
    echo 'Replica not found!', PHP_EOL;
    exit(1);
}

$db->beginTransaction();
$db->update(
    Chronicle::getTableNameUnquoted('replication_sources'),
    [
        'publickey' => $publicKey
    ],
    [
        'uniqueid' => $id
    ]
);
if ($db->commit()) {
    echo 'Updated successfully!', PHP_EOL;
}
