<?php
declare(strict_types=1);

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

/** @var array $settings */
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

// Pass database instance to Chronicle
Chronicle::setDatabase($db);

/**
 * @var Getopt $getopt
 *
 * This defines the Command Line options.
 *
 * These two are equivalent:
 *     php create-client.php -p foo
 *     php create-client php --public=key=foo
 */
$getopt = new Getopt([
    new Option('p', 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option('c', 'comment', Getopt::OPTIONAL_ARGUMENT),
    new Option('j', 'json', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'administrator', Getopt::OPTIONAL_ARGUMENT),
    new Option('i', 'instance', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->process();

/** @var string $publicKey */
$publicKey = $getopt->getOption('publickey');
/** @var string $comment */
$comment = $getopt->getOption('comment') ?? '';
/** @var bool $admin */
$admin = $getopt->getOption('administrator') ?? false;
/** @var bool $json */
$json = $getopt->getOption('json') ?? false;
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

if (empty($publicKey)) {
    echo 'Usage:', PHP_EOL, "\t",
        'php create-client.php -p publickeygoeshere [-c comment] [--administrator]', PHP_EOL, PHP_EOL;
    exit(1);
}

/** @var SigningPublicKey $publicKeyObj */
// Make sure it's a valid public key:
try {
    $publicKeyObj = new SigningPublicKey(
        Base64UrlSafe::decode($publicKey)
    );
} catch (\Throwable $ex) {
    if ($json) {
        echo json_encode([
            'status' => false,
            'message' => $ex->getMessage(),
            'data' => [
                'trace' => $ex->getTrace()
            ]
        ]), PHP_EOL;
    } else {
        echo $ex->getMessage(), PHP_EOL;
    }
    exit(1);
}

// Generate a unique ID for the user:
/** @var string $newPublicId */
$newPublicId = Base64UrlSafe::encode(\random_bytes(24));

// Disable escaping for SQLite
/** @var boolean $isSQLite */
$isSQLite = strpos($settings['database']['dsn'] ?? '', 'sqlite:') !== false;

$db->beginTransaction();
$db->insert(
    Chronicle::getTableNameUnquoted('clients', $isSQLite),
    [
        'isAdmin' => !empty($admin),
        'publicid' => $newPublicId,
        'publickey' => $publicKey
    ]
);
if ($db->commit()) {
    if ($json) {
        echo json_encode([
            'status' => true,
            'message' => 'Client successfully created!',
            'data' => [
                'publicId' => $newPublicId,
                'administrator' => !empty($admin)
            ]
        ]);
        exit(0);
    }
    // Success.
    if (!empty($admin)) {
        echo "\t" . 'Client (' . $newPublicId . ') created successfully with administrative privileges!', PHP_EOL;
    } else {
        echo "\t" . 'Client (' . $newPublicId . ') created successfully!', PHP_EOL;
    }
} else {
    $db->rollBack();
    /** @var array<int, string> $errorInfo */
    $errorInfo = $db->errorInfo();

    if ($json) {
        echo json_encode([
            'status' => false,
            'message' => $errorInfo[0],
            'data' => []
        ]), PHP_EOL;
    } else {
        echo $errorInfo[0], PHP_EOL;
    }
    exit(1);
}
