<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use Cache\Adapter\Memcached\MemcachedCachePool;
use ParagonIE\Chronicle\Exception\CacheMisuseException;
use ParagonIE\ConstantTime\Base32;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Http\Headers;
use Slim\Http\Response;
use Slim\Http\Stream;

/**
 * Class ResponseCache
 * @package ParagonIE\Chronicle
 */
class ResponseCache
{
    /** @var string $cacheKey */
    private $cacheKey;

    /** @var int|null $lifetime */
    private $lifetime;

    /** @var MemcachedCachePool $memcached */
    private $memcached;

    /**
     * ResponseCache constructor.
     * @param int $lifetime
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws CacheMisuseException
     */
    public function __construct(int $lifetime = 0)
    {
        if (!self::isAvailable()) {
            throw new CacheMisuseException('Memcached is not installed.');
        }
        $client = new \Memcached();
        $client->addServer('localhost', 11211);
        if ($lifetime > 0) {
            $this->lifetime = $lifetime;
        }
        $this->memcached = new MemcachedCachePool($client);
        $this->loadCacheKey();
    }

    /**
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws CacheMisuseException
     */
    public function loadCacheKey()
    {
        if (isset($this->cacheKey)) {
            return $this->cacheKey;
        }
        if ($this->memcached->hasItem('ChronicleCacheKey')) {
            $item = $this->memcached->getItem('ChronicleCacheKey');
            return $item->get();
        }
        try {
            $key = sodium_crypto_shorthash_keygen();
        } catch (\Throwable $ex) {
            throw new CacheMisuseException('CSPRNG failure', 0, $ex);
        }
        $item = $this->memcached->getItem('ChronicleCacheKey');
        $item->set($key);
        $item->expiresAfter(null);
        $this->memcached->save($item);
        return $key;
    }

    /**
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('memcached') && class_exists('Memcached');
    }

    /**
     * @param string $input
     * @return string
     * @throws CacheMisuseException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \SodiumException
     */
    public function getCacheKey(string $input): string
    {
        return 'Chronicle|' . Base32::encodeUnpadded(
            sodium_crypto_shorthash($input, $this->loadCacheKey())
        );
    }

    /**
     * @param string $uri
     * @return Response|null
     * @throws CacheMisuseException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \SodiumException
     */
    public function loadResponse(string $uri)
    {
        $key = $this->getCacheKey($uri);
        if (!$this->memcached->hasItem($key)) {
            return null;
        }
        $item = $this->memcached->getItem($key);
        $cached = $item->get();
        if (!is_string($cached)) {
            return null;
        }
        return $this->deserializeResponse($cached);
    }

    /**
     * @param string $uri
     * @param ResponseInterface $response
     * @throws CacheMisuseException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \SodiumException
     */
    public function saveResponse(string $uri, ResponseInterface $response)
    {
        $key = $this->getCacheKey($uri);
        $item = $this->memcached->getItem($key);
        $item->set($this->serializeResponse($response));
        $item->expiresAfter($this->lifetime);
        $this->memcached->save($item);
    }

    /**
     * @param string $serialized
     * @return Response
     */
    public function deserializeResponse(string $serialized): Response
    {
        $decoded = json_decode($serialized, true);
        return new Response(
            (int) $decoded['status'],
            new Headers($decoded['headers']),
            self::fromString($decoded['body'])
        );
    }

    /**
     * Create a Stream object from a string.
     *
     * @param string $input
     * @return StreamInterface
     * @throws \Error
     */
    public static function fromString(string $input): StreamInterface
    {
        /** @var resource $stream */
        $stream = \fopen('php://temp', 'w+');
        if (!\is_resource($stream)) {
            throw new \Error('Could not create stream');
        }
        \fwrite($stream, $input);
        \rewind($stream);
        return new Stream($stream);
    }

    /**
     * @param ResponseInterface $response
     * @return string
     */
    public function serializeResponse(ResponseInterface $response): string
    {
        return json_encode([
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $response->getBody()->getContents()
        ]);
    }
}
