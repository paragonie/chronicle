<?php
declare(strict_types=1);

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\EasyDB\{
    EasyDB,
    Factory
};
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\ConstantTime\Base64UrlSafe;

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

/** @var int $argc */
/** @var array<int, string> $v */
$v = $argv; // Seriously, Psalm?

if ($argc < 2) {
    echo 'Usage: ', PHP_EOL , "\t",
    'php write_file.php /path/to/input_file [/path/to/keyfile]', PHP_EOL;
    exit(1);
}

$filePath = $v[1];
$keyFilePath = ($argc > 2
    ? $v[2]
    : $root . '/local/client.key'
);

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
Chronicle::setDatabase($db);

if (!\is_readable($filePath)) {
    echo 'File not readable (or doesn\'t exist): ', $filePath, PHP_EOL;
    exit(1);
}
if (!\is_readable($keyFilePath)) {
    echo 'Key file not readable (or doesn\'t exist): ', $keyFilePath, PHP_EOL;
    exit(1);
}

/** @var string $fileContents */
$fileContents = \file_get_contents($filePath);

/** @var array $keyFile */
$keyFile = \json_decode(
    (string) \file_get_contents($keyFilePath),
    true
);

$signature = \sodium_crypto_sign_detached(
    $fileContents,
    Base64UrlSafe::decode($keyFile['secret-key'])
);

Chronicle::extendBlakechain(
    $fileContents,
    Base64UrlSafe::encode($signature),
    new SigningPublicKey(Base64UrlSafe::decode($keyFile['public-key']))
);
