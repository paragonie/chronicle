<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\HandlerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;

/**
 * Class Revoke
 * @package ParagonIE\Chronicle\Handlers
 */
class Revoke implements HandlerInterface
{
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
            if (!$request->getAttribute('administrator')) {
                throw new \Error('Unprivileged request');
            }
        } else {
            throw new \TypeError('Something unexpected happen when attempting to publish.');
        }

        $result = [];

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            $result,
            Chronicle::getSigningKey(),
            $response->getHeaders(),
            $response->getProtocolVersion()
        );
    }
}
