<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\CliTests;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;

if (file_exists(__DIR__ . '/client.json')) {
    exit(0);
}

require_once dirname(__DIR__) . '/cli-include.php';

Chronicle::getDatabase()->beginTransaction();

$signingKey = SigningSecretKey::generate();
$publicKey = $signingKey->getPublickey()->getString();

$ret = \file_put_contents(
    __DIR__ . '/client.json',
    \json_encode([
        'secret-key' => $signingKey->getString(),
        'public-key' => $signingKey->getPublickey()->getString()
    ])
);

if (is_bool($ret)) {
    echo 'Could not save temporary client', PHP_EOL;
    Chronicle::getDatabase()->rollBack();
    exit(255);
}

Chronicle::getDatabase()->insert(
    'chronicle_clients',
    [
        'isAdmin' => false,
        'publicid' => 'CLI-testing-user',
        'publicKey' => $publicKey
    ]
);

Chronicle::getDatabase()->commit();
