<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\HandlerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Http\Request;

/**
 * Class Publish
 * @package ParagonIE\Chronicle\Handlers
 */
class Publish implements HandlerInterface
{
    /**
     * @var App
     */
    protected $app;

    /**
     * Publish constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws \Error
     * @throws \TypeError
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        if ($request instanceof Request) {
            if (!$request->getAttribute('authenticated')) {
                throw new \Error('Unauthenticated request');
            }
        } else {
            throw new \TypeError('Something unexpected happen when attempting to publish.');
        }

        $result = Chronicle::extendBlakechain(
            (string) $request->getBody()
        );

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            $result,
            Chronicle::getSigningKey(),
            $response->getHeaders(),
            $response->getProtocolVersion()
        );
    }
}
