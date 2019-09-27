<?php
declare(strict_types=1);
namespace Tests\unit;

use ParagonIE\Chronicle\ResponseCache;
use PHPUnit\Framework\TestCase;
use Slim\Http\Headers;
use Slim\Http\Response;

/**
 * Class ResponseCacheTest
 * @package Tests\unit
 */
class ResponseCacheTest extends TestCase
{
    protected $cache;

    public function setUp()
    {
        if (!ResponseCache::isAvailable()) {
            $this->markTestSkipped('Memcached is not installed');
        }
        $this->cache = new ResponseCache();
    }

    public function testCache()
    {
        $response = new Response(
            200,
            new Headers(['Content-Type' => 'text/plain; charset=UTF-8']),
            ResponseCache::fromString('test message')
        );
        $this->cache->saveResponse('/test-response', $response);
        $loaded = $this->cache->loadResponse('/test-response');
        $this->assertEquals(
            $loaded->getStatusCode(),
            $response->getStatusCode()
        );
        $this->assertEquals(
            $loaded->getHeaders(),
            $response->getHeaders()
        );
        $this->assertEquals(
            (string) $loaded->getBody(),
            (string) $response->getBody()
        );
    }

    public function testSerialize()
    {
        $response = new Response(
            200,
            new Headers(['Content-Type' => 'text/plain; charset=UTF-8']),
            ResponseCache::fromString('test message')
        );
        $serialized = $this->cache->serializeResponse($response);
        $this->assertSame(
            '{"status":200,"headers":{"Content-Type":["text\/plain; charset=UTF-8"]},"body":"test message"}',
            $serialized
        );
        $deserialized = $this->cache->deserializeResponse($serialized);
        $this->assertSame(200, $deserialized->getStatusCode());
        $this->assertSame(['text/plain; charset=UTF-8'], $deserialized->getHeader('Content-Type'));
        $this->assertSame('test message', $deserialized->getBody()->getContents());
    }
}
