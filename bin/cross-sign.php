<?php
declare(strict_types=1);

/**
 * This script sets up cross-signing to another Chronicle
 */

use ParagonIE\EasyDB\Factory;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use tflori\Getopt\{
    Getopt,
    Option
};

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

if (!\is_readable($root . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

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

/**
 * @var Getopt
 *
 * This defines the Command Line options.
 */
$getopt = new Getopt([
    new Option(null, 'url', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'clientid', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'push-after', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'push-days', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'name', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->parse();

$url = $getopt->getOption('url');
$publicKey = $getopt->getOption('publickey');
$clientId = $getopt->getOption('clientid');
$pushAfter = $getopt->getOption('push-after') ?? null;
$pushDays = $getopt->getOption('push-days') ?? null;
$name = $getopt->getOption('name') ?? (new DateTime())->format(DateTime::ATOM);

$fields = [];
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
if ($db->exists('SELECT * FROM chronicle_xsign_targets WHERE name = ?', $name)) {
    // Update an existing cross-sign target
    $db->update('chronicle_xsign_targets', $fields, ['name' => $name]);
} else {
    // Create a new cross-sign target
    if (empty($url) || empty($publicKey)) {
        $db->rollBack();
        echo '--url and --publickey are mandatory for new cross-sign targets', PHP_EOL;
        exit(1);
    }
    $fields['name'] = $name;
    $db->insert('chronicle_xsign_targets', $fields);
}

if (!$db->commit()) {
    $db->rollBack();
    echo $db->errorInfo()[0], PHP_EOL;
    exit(1);
}
