<?php
declare(strict_types=1);

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

/*
Future Genesis blocks will have a NULL prevhash, thereby allowing UNIQUE
FOREIGN KEY constraints to be created on prevhash pointing to a previous
row's currahsh.
 */

/** @var string $filePath */
$filePath = (string) $v[1];
/** @var string $keyFilePath */
$keyFilePath = (string) ($argc > 2
    ? (string) $v[2]
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

/** @var EasyDB $db */
$db = Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);
\ParagonIE\Chronicle\Chronicle::setDatabase($db);

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

/** @var string $signature */
$signature = \sodium_crypto_sign_detached(
    $fileContents,
    Base64UrlSafe::decode($keyFile['secret-key'])
);

/*
{
    "secret-key": "GzEQe7YbPL2CIca7I_Tg0c4DAAyvPB_Bt6m2y4jdQwUtkB_wW5Duwv54fDPWL0XD5f5xkzeRktSeE54CK67GDQ==",
    "public-key": "LZAf8FuQ7sL-eHwz1i9Fw-X-cZM3kZLUnhOeAiuuxg0="
}
*/

\ParagonIE\Chronicle\Chronicle::extendBlakechain(
    $fileContents,
    Base64UrlSafe::encode($signature),
    new SigningPublicKey(Base64UrlSafe::decode($keyFile['public-key']))
);
