<?php
declare(strict_types=1);

/**
 * This script sets up replication of another Chronicle
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
    new Option(null, 'name', Getopt::REQUIRED_ARGUMENT),
]);
$getopt->parse();

$url = $getopt->getOption('url');
$publicKey = $getopt->getOption('publickey');
$name = $getopt->getOption('name');
if (!isset($url, $publicKey, $name)) {
    echo "Not enough data. Please specify:\n",
    "\t--name\n",
    "\t--publickey\n",
    "\t--url\n";
    exit(1);
}

try {
    $publicKeyObj = new SigningPublicKey(
        Base64UrlSafe::decode($publicKey)
    );
} catch (\Throwable $ex) {
    echo $ex->getMessage(), PHP_EOL;
    exit(1);
}

$db->beginTransaction();
$db->insert('chronicle_replication_sources', [
    'name' => $name,
    'uniqueid' => Base64UrlSafe::encode(random_bytes(33)),
    'publickey' => $publicKey,
    'url' => $url
]);
if (!$db->commit()) {
    $db->rollBack();
    echo $db->errorInfo()[0], PHP_EOL;
    exit(1);
}
