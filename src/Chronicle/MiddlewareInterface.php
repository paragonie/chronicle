<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Interface MiddlewareInterface
 * @package ParagonIE\Chronicle
 */
interface MiddlewareInterface
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next
    );
}
