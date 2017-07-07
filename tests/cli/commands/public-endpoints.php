<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\CliTests;

use GuzzleHttp\Client;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use ParagonIE\Sapient\Sapient;
use GuzzleHttp\Psr7\Request;

require_once dirname(__DIR__) . '/command-preamble.php';

/**
 * @global string $baseUrl
 * @global array $client
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

/*
    $this->get('/lasthash', 'lookup.lasthash');
    $this->get('/lookup/[{hash}]', 'lookup.hash');
    $this->get('/since/[{hash}]', 'lookup.since');
    $this->get('/export', 'lookup.export');
 */

// Export:
$request = new Request('GET', $baseUrl . '/chronicle/export', []);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}

$hash = $response['results'][0]['summary'];

$request = new Request('GET', $baseUrl . '/chronicle/lasthash', []);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}
$lastHash = $response['results']['summary-hash'];

$request = new Request('GET', $baseUrl . '/chronicle/since/' . \urlencode($hash), []);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);

if (\hash_equals($lastHash, $hash)) {
    if (count($response['results']) > 0) {
        echo 'Race condition!', PHP_EOL;
    }
} elseif (count($response['results']) === 0) {
    var_dump($response);
    exit(255);
}

$request = new Request('GET', $baseUrl . '/chronicle/lookup/' . \urlencode($hash), []);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}

$request = new Request('GET', $baseUrl . '/chronicle/lookup/' . \urlencode($lastHash), []);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}

$request = new Request('GET', $baseUrl . '/chronicle/replica', []);
$response = $sapient->decodeSignedJsonResponse(
    $http->send($request),
    $serverPublicKey
);
if ($response['status'] !== 'OK') {
    var_dump($response);
    exit(255);
}

echo 'OK.', PHP_EOL;
exit(0);
