# How to Write to Your Chronicle

**Important:** Any HTTP client that signs messages with Ed25519 as
defined in the [Sapient specification](https://github.com/paragonie/sapient/blob/master/docs/Specification.md)
can write to a Chronicle, regardless of what programming language
you're using. If you're not a PHP developer, don't be discouraged
just because our examples are in PHP.

-----

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

The above snippet assumes that you've already [created a keypair](01-setup.md#generating-client-keys)
for the client, and loaded it like so:

```php
<?php
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;

/**
 * @global string $secretKeyText 
 */

$secret = new SigningSecretKey(
    Base64UrlSafe::decode($secretKeyText)
);
```

This creates a [`SigningSecretKey`](https://github.com/paragonie/sapient/blob/master/docs/Internals/CryptographyKey.md)
object, which is used by [Sapient](https://github.com/paragonie/sapient).
