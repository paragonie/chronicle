<?php
$root = \dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$signingKey = \ParagonIE\Sapient\CryptographyKeys\SigningSecretKey::generate();

\file_put_contents(
    $root . '/local/signing-secret.key',
    $signingKey->getString()
);

$localSettings = [
    'database' => [
        'dsn' => $root . '/local/chronicle.sql'
    ],
    'signing-public-key' => $signingKey->getPublicKey()->getString()
];

\file_put_contents(
    $root . '/local/settings.json',
    \json_encode($localSettings, JSON_PRETTY_PRINT)
);
