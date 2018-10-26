<?php
declare(strict_types=1);

/**
 * This script sets up cross-signing to another Chronicle
 */

use GetOpt\{
    GetOpt,
    Option
};
use ParagonIE\EasyDB\{
    EasyDB,
    Factory
};
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

/**
 * @var GetOpt $getopt
 *
 * This defines the Command Line options.
 */
$getopt = new GetOpt([
    new Option(null, 'url', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'clientid', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'push-after', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'push-days', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'name', Getopt::OPTIONAL_ARGUMENT),
    new Option('i', 'instance', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->process();

/** @var string $url */
$url = $getopt->getOption('url');
/** @var string $publicKey */
$publicKey = $getopt->getOption('publickey');
/** @var string $clientId */
$clientId = $getopt->getOption('clientid');
/** @var string|null $pushAfter $pushAfter */
$pushAfter = $getopt->getOption('push-after') ?? null;
/** @var string|null $pushDays */
$pushDays = $getopt->getOption('push-days') ?? null;
/** @var string $name */
$name = $getopt->getOption('name') ?? (new DateTime())->format(DateTime::ATOM);
/** @var string $instance */
$instance = $getopt->getOption('instance') ?? '';

try {
    if (!empty($instance)) {
        /** @var array<string, string> $instances */
        $instances = $settings['instances'];
        if (!\array_key_exists($instance, $instances)) {
            throw new InstanceNotFoundException(
                'Instance ' . $instance . ' not found'
            );
        }
        Chronicle::setTablePrefix($instances[$instance]);
    }
} catch (InstanceNotFoundException $ex) {
    echo $ex->getMessage(), PHP_EOL;
    exit(1);
}

/** @var array<string, string> $fields */
$fields = [];
/** @var array<string, int> $policy */
$policy = [];
if ($pushAfter) {
    $policy['push-after'] = (int) $pushAfter;
}
if ($pushDays) {
    $policy['push-days'] = (int) $pushDays;
}
if (empty($policy)) {
    echo "Not enough data. Please specify one of:\n",
        "\t--push-days\n",
        "\t--push-after\n";
    exit(1);
}
$fields['policy'] = \json_encode($policy);
if ($url) {
    $fields['url'] = $url;
}
if (is_string($publicKey)) {
    try {
        /** @var SigningPublicKey $publicKeyObj */
        $publicKeyObj = new SigningPublicKey(
            Base64UrlSafe::decode($publicKey)
        );
    } catch (\Throwable $ex) {
        echo $ex->getMessage(), PHP_EOL;
        exit(1);
    }
    $fields['publickey'] = $publicKey;
}
$fields['clientid'] = $clientId;

$db->beginTransaction();
/** @var string $table */
$table = Chronicle::getTableName('xsign_targets');
if ($db->exists('SELECT * FROM ' . $table . ' WHERE name = ?', $name)) {
    // Update an existing cross-sign target
    $db->update($table, $fields, ['name' => $name]);
} else {
    // Create a new cross-sign target
    if (empty($url) || empty($publicKey)) {
        $db->rollBack();
        echo '--url and --publickey are mandatory for new cross-sign targets', PHP_EOL;
        exit(1);
    }
    $fields['name'] = $name;
    $db->insert($table, $fields);
}

if (!$db->commit()) {
    $db->rollBack();
    /** @var array<int, string> $errorInfo */
    $errorInfo = $db->errorInfo();
    echo $errorInfo[0], PHP_EOL;
    exit(1);
}
