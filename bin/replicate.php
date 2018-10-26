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
 * @var Getopt
 *
 * This defines the Command Line options.
 */
$getopt = new GetOpt([
    new Option(null, 'url', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option(null, 'name', Getopt::REQUIRED_ARGUMENT),
    new Option('i', 'instance', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->process();

/** @var string $url */
$url = $getopt->getOption('url');
/** @var string $publicKey */
$publicKey = $getopt->getOption('publickey');
/** @var string $name */
$name = $getopt->getOption('name');
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
$db->insert(Chronicle::getTableName('replication_sources'), [
    'name' => $name,
    'uniqueid' => Base64UrlSafe::encode(random_bytes(33)),
    'publickey' => $publicKey,
    'url' => $url
]);
if (!$db->commit()) {
    $db->rollBack();
    /** @var array<int, string> $errorInfo */
    $errorInfo = $db->errorInfo();
    echo $errorInfo[0], PHP_EOL;
    exit(1);
}
