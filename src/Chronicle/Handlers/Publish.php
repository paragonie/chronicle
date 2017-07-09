<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    Exception\AccessDenied,
    Exception\ClientNotFound,
    Exception\SecurityViolation,
    HandlerInterface,
    Scheduled
};
use ParagonIE\Sapient\Sapient;
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};
use Slim\Http\Request;

/**
 * Class Publish
 * @package ParagonIE\Chronicle\Handlers
 */
class Publish implements HandlerInterface
{
    /**
     * The handler gets invoked by the router. This accepts a Request
     * and returns a Response.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     *
     * @throws AccessDenied
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        // Sanity checks
        if ($request instanceof Request) {
            if (!$request->getAttribute('authenticated')) {
                return Chronicle::errorResponse(
                    $response,
                    'Access denied',
                    403
                );
            }
        } else {
            return Chronicle::errorResponse(
                $response,
                'Something unexpected happen when attempting to publish.',
                500
            );
        }

        try {
            Chronicle::validateTimestamps($request, 'now');
        } catch (SecurityViolation $ex) {
            // Invalid timestamp. Possibly a replay attack.
            return Chronicle::errorResponse(
                $response,
                $ex->getMessage(),
                $ex->getCode()
            );
        } catch (\Throwable $ex) {
            // We aren't concerned with non-security failures here.
        }

        // Get the public key and signature; store this information:
        list($publicKey, $signature) = $this->getHeaderData($request);

        $result = Chronicle::extendBlakechain(
            (string) $request->getBody(),
            $signature,
            $publicKey
        );

        // If we need to do a cross-sign, do it now:
        (new Scheduled())->doCrossSigns();

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $result
            ],
            Chronicle::getSigningKey(),
            $response->getHeaders(),
            $response->getProtocolVersion()
        );
    }

    /**
     * Get the SigningPublicKey and signature for this message.
     *
     * @param RequestInterface $request
     * @return array
     *
     * @throws SecurityViolation
     * @throws ClientNotFound
     */
    public function getHeaderData(RequestInterface $request): array
    {
        $clientHeader = $request->getHeader(Chronicle::CLIENT_IDENTIFIER_HEADER);
        if (!$clientHeader) {
            throw new SecurityViolation('No client header provided');
        }
        $signatureHeader = $request->getHeader(Sapient::HEADER_SIGNATURE_NAME);
        if (!$signatureHeader) {
            throw new SecurityViolation('No signature provided');
        }

        if (\count($signatureHeader) === 1 && count($clientHeader) === 1) {
            return [
                Chronicle::getClientsPublicKey(\array_shift($clientHeader)),
                \array_shift($signatureHeader)
            ];
        }
        throw new ClientNotFound('Could not find the correct client');
    }
}
