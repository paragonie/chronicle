<?php
namespace ParagonIE\Chronicle\Handlers;

use GuzzleHttp\Exception\GuzzleException;
use ParagonIE\Chronicle\{
    Chronicle,
    Exception\ChainAppendException,
    Exception\ClientNotFound,
    Exception\FilesystemException,
    Exception\SecurityViolation,
    Exception\TargetNotFound,
    HandlerInterface,
    Scheduled
};
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\Exception\InvalidMessageException;
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
     * @throws ChainAppendException
     * @throws ClientNotFound
     * @throws FilesystemException
     * @throws InvalidMessageException
     * @throws SecurityViolation
     * @throws TargetNotFound
     * @throws GuzzleException
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
        /**
         * @var SigningPublicKey $publicKey
         * @var string $signature
         */
        list($publicKey, $signature) = $this->getHeaderData($request);

        $requestBody = (string) $request->getBody();
        $this->throwIfUnsafe($requestBody);

        $result = Chronicle::extendBlakechain(
            $requestBody,
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

    /**
     * @param string $data
     * @throws ChainAppendException
     */
    public function throwIfUnsafe(string $data)
    {
        $encoded = json_encode(['data' => $data]);
        if (!\is_string($encoded)) {
            throw new ChainAppendException(
                'Stored data cannot safely be returned in our JSON API: ' .
                \json_last_error_msg()
            );
        }
    }
}
