<?php

use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;

require '../cli-autoload.php';

/* This generates a new secret key from your kernel's CSPRNG */
$signingSecretKey = SigningSecretKey::generate();

echo json_encode(
    [
        'secret-key' => $signingSecretKey->getString(),
        'public-key' => $signingSecretKey->getPublicKey()->getString()
    ],
    JSON_PRETTY_PRINT
), PHP_EOL;