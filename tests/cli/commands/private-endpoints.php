<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\CliTests;

use GuzzleHttp\Client;
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SealingPublicKey;
use ParagonIE\Sapient\CryptographyKeys\SealingSecretKey;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use ParagonIE\Sapient\Sapient;
use GuzzleHttp\Psr7\Request;

require_once dirname(__DIR__) . '/command-preamble.php';

/**
 * @global string $baseUrl
 * @global array $client
 * @global array $clientAdmin
 * @global Client $http
 * @global Sapient $sapient
 * @global SigningPublickey $serverPublicKey
 */
if (
    !($http instanceof Client) ||
    !($sapient instanceof Sapient) ||
    !($serverPublicKey instanceof SigningPublicKey) ||
    !($client['secret-key'] instanceof SigningSecretKey) ||
    !($client['public-key'] instanceof SigningPublicKey)
) {
    var_dump([
        ($http instanceof Client),
        ($sapient instanceof Sapient),
        ($serverPublicKey instanceof SigningPublicKey),
        ($client['secret-key'] instanceof SigningSecretKey),
        ($client['public-key'] instanceof SigningPublicKey)
    ]);
    echo 'Include failed', PHP_EOL;
    exit(1);
}

$request = $sapient->createSignedJsonRequest(
    'POST',
    $baseUrl . '/chronicle/publish',
    [
        'now' => (new \DateTime())->format(\DateTime::ATOM),
        'test' => 'This is a test entry. DELETE ME AFTER. ' . Base64UrlSafe::encode(random_bytes(33))
    ],
    $client['secret-key'],
    [
        Chronicle::CLIENT_IDENTIFIER_HEADER => 'CLI-testing-user'
    ]
);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}

$sealingData = \json_decode(\file_get_contents(dirname(__DIR__) . '/sealing.json'), true);
$sealing = [
    'secret-key' => new SealingSecretKey(Base64UrlSafe::decode($sealingData['secret-key'])),
    'public-key' => new SealingPublicKey(Base64UrlSafe::decode($sealingData['public-key']))
];
$request = $sapient->createSealedJsonRequest(
    'POST',
    $baseUrl . '/chronicle/publish',
    [
        'now' => (new \DateTime())->format(\DateTime::ATOM),
        'test' => 'This is a test entry. DELETE ME AFTER. ' . Base64UrlSafe::encode(random_bytes(33))
    ],
    $sealing['public-key'],
    [
        Chronicle::CLIENT_IDENTIFIER_HEADER => 'CLI-testing-user'
    ]
);
$signed = $sapient->signRequest($request, $client['secret-key']);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($signed),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}

$registeredClientSecretKey = SigningSecretKey::generate();

$request = $sapient->createSignedJsonRequest(
    'POST',
    $baseUrl . '/chronicle/register',
    [
        'publickey' => $registeredClientSecretKey->getPublickey()->getString(),
        'comment' => 'this is a comment',
    ],
    $clientAdmin['secret-key'],
    [
        Chronicle::CLIENT_IDENTIFIER_HEADER => 'CLI-admin-user'
    ]
);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}

$request = $sapient->createSignedJsonRequest(
    'POST',
    $baseUrl . '/chronicle/revoke',
    [
        'clientid' => $response['results']['client-id'],
        'publickey' => $registeredClientSecretKey->getPublickey()->getString(),
    ],
    $clientAdmin['secret-key'],
    [
        Chronicle::CLIENT_IDENTIFIER_HEADER => 'CLI-admin-user'
    ]
);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}
