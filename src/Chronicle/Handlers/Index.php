<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\{
    Chronicle,
    HandlerInterface
};
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Index
 *
 * URI endpoint that lists the endpoints available
 *
 * @package ParagonIE\Chronicle\Handlers
 */
class Index implements HandlerInterface
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return mixed
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'version' => Chronicle::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $this->getRoutes($request)
            ],
            Chronicle::getSigningKey()
        );
    }

    /**
     * @param RequestInterface $request
     * @return array
     */
    public function getRoutes(RequestInterface $request): array
    {
        $allowed = [
            [
                'uri' => '/chronicle/lasthash',
                'description' => 'Get information about the latest entry in this Chronicle'
            ], [
                'uri' => '/chronicle/since/[{hash}]',
                'description' => 'List all new entries since a given hash'
            ], [
                'uri' => '/chronicle/export',
                'description' => 'Export the entire Chronicle'
            ], [
                'uri' => '/chronicle/',
                'description' => 'API method description'
            ],
        ];

        if ($request->hasHeader(Chronicle::CLIENT_IDENTIFIER_HEADER)) {
            $headers = $request->getHeader(Chronicle::CLIENT_IDENTIFIER_HEADER);
            $clientId = array_shift($headers);
            $dbQueryResult = Chronicle::getDatabase()->row(
                'SELECT * FROM chronicle_clients WHERE publicid = ?',
                $clientId
            );
            if (!empty($dbQueryResult)) {
                $allowed []= [
                    'uri' => '/chronicle/publish',
                    'description' => 'Publish a new message to this Chronicle.',
                    'note' => 'Approved clients only.'
                ];
                if (!empty($dbQueryResult['isAdmin'])) {
                    $allowed []= [
                        'uri' => '/chronicle/register',
                        'description' => 'Approve a new client.',
                        'note' => 'Administrators only'
                    ];
                    $allowed []= [
                        'uri' => '/chronicle/revoke',
                        'description' => 'Revoke a client\'s access',
                        'note' => 'Administrators only'
                    ];
                }
            }
        }
        return $allowed;
    }
}
