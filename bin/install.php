<?php
declare(strict_types=1);

use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;

/** @var string $root */
$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

// Generate a signing key.
/** @var SigningSecretKey $signingKey */
$signingKey = SigningSecretKey::generate();

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
    // Map 'channel-name' => 'table_prefix'
    'instances' => [
        '' => ''
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
