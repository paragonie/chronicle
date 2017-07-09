<?php
declare(strict_types=1);

$root = \dirname(__DIR__);
require_once $root . '/cli-autoload.php';

// Generate a signing key.
$signingKey = \ParagonIE\Sapient\CryptographyKeys\SigningSecretKey::generate();

// Store the signing key:
\file_put_contents(
    $root . '/local/signing-secret.key',
    $signingKey->getString()
);

// Write the default settings to the local settings file.
$localSettings = [
    'database' => [
        'dsn' => 'sqlite:' . $root . '/local/chronicle.sql'
    ],
    'publish-new-clients' => true,
    'publish-revoked-clients' => true,
    // The maximum window of opportunity for replay attacks:
    'request-timeout' => '10 minutes',
    'scheduled-attestation' => '7 days',
    'signing-public-key' => $signingKey->getPublicKey()->getString()
];

\file_put_contents(
    $root . '/local/settings.json',
    \json_encode($localSettings, JSON_PRETTY_PRINT)
);
