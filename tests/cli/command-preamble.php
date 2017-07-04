<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\CliTests;

use GuzzleHttp\Client;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use ParagonIE\Sapient\Sapient;

require_once __DIR__ . '/cli-include.php';

/**
 * @global Client $http
 * @global Sapient $sapient
 * @global SigningPublickey $serverPublicKey
 */
if (!($http instanceof Client) || !($sapient instanceof Sapient) || !($serverPublicKey instanceof SigningPublicKey)) {
    echo 'Include failed', PHP_EOL;
    exit(1);
}

if (!\is_readable((__DIR__ . '/client.json'))) {
    echo 'client.json is not found!', PHP_EOL;
    exit(255);
}
$clientData = \json_decode(\file_get_contents(__DIR__ . '/client.json'), true);
$client = [
    'secret-key' => new SigningSecretKey(Base64UrlSafe::decode($clientData['secret-key'])),
    'public-key' => new SigningPublicKey(Base64UrlSafe::decode($clientData['public-key']))
];

/*
$request = $sapient->createSignedJsonRequest(
    'POST',
    $baseUrl . '/chronicle/export',
    [
        'now' => (new \DateTime())->format(\DateTime::ATOM)
    ],
    $client['secret-key']
);
*/
