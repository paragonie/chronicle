<?php

use ParagonIE\EasyDB\Factory;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use tflori\Getopt\{
    Getopt,
    Option
};

$root = \dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

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

$getopt = new Getopt([
    new Option('p', 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option('c', 'comment', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->parse();

$publicKey = $getopt->getOption('publickey');
$comment = $getopt->getOption('comment') ?? '';

try {
    $publicKeyObj = new SigningPublicKey(
        Base64UrlSafe::decode($publicKey)
    );
} catch (\Throwable $ex) {
    echo $ex->getMessage(), PHP_EOL;
    exit(1);
}

$newPublicId = Base64UrlSafe::encode(\random_bytes(24));

$db->beginTransaction();
$db->insert(
    'chronicle_clients',
    [
        'publicid' => $newPublicId,
        'publicKey' => $publicKey
    ]
);
if ($db->commit()) {
    echo "\t" . 'Client (' . $newPublicId . ') created successfully!', PHP_EOL;
} else {
    $db->rollBack();
    echo $db->errorInfo()[0], PHP_EOL;
    exit(1);
}
