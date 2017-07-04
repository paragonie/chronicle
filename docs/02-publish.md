# How to Write to Your Chronicle

If you have an HTTP client, such as Guzzle, with a [Sapient](https://github.com/paragonie/sapient)
adapter, all you need to do is send messages like so:

```php
<?php

use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use ParagonIE\Sapient\Sapient;
use GuzzleHttp\Client;

/**
 * @global SigningSecretKey $secret
 * @global Sapient $sapient
 * @global Client $http 
 */

$message = 'lorem ipsum';

// Create a signed HTTP request (PSR-7)
$request = $sapient->createSignedRequest(
    'POST',
    'http://your-chronicle-instance.localhost/chronicle/publish',
    $message,
    $secret
);

// Send the request to the Chronicle
$response = $http->send($request);
```

If the request was successful, the JSON response you receive should
include a valid signature.

```php
<?php

use GuzzleHttp\Psr7\Response;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\Exception\{
    HeaderMissingException,
    InvalidMessageException
};

/**
 * @global Response $response
 * @global SigningPublicKey $publicKey
 */

try {
    $decoded = $sapient->decodeSignedJsonResponse($response, $publicKey);
} catch (InvalidMessageException $ex) {
    // Invalid signature
} catch (HeaderMissingException $ex) {
    // Not signed
}
```
