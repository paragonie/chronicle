<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use ParagonIE\Blakechain\Blakechain;
use ParagonIE\Chronicle\Exception\{
    ChainAppendException,
    ClientNotFound,
    FilesystemException,
    HTTPException,
    SecurityViolation,
    TimestampNotProvided
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\EasyDB\EasyDB;
use ParagonIE\Sapient\Adapter\Slim;
use ParagonIE\Sapient\CryptographyKeys\{
    SigningPublicKey,
    SigningSecretKey
};
use ParagonIE\Sapient\Sapient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Chronicle
 * @package ParagonIE\Chronicle
 */
class Chronicle
{
    /** @var EasyDB $easyDb */
    protected static $easyDb;

    /** @var array $settings */
    protected static $settings;

    /** @var SigningSecretKey $signingKey */
    protected static $signingKey;

    /* This constant is the name of the header used to find the
       corresponding public key: */
    const CLIENT_IDENTIFIER_HEADER = 'Chronicle-Client-Key-ID';

    /* This constant denotes the Chronicle version running, server-side */
    const VERSION = '1.0.x';

    /**
     * This extends the Blakechain with an arbitrary message, signature, and
     * public key.
     *
     * @param string $body
     * @param string $signature
     * @param SigningPublickey $publicKey
     * @return array<string, string>
     * @throws ChainAppendException
     */
    public static function extendBlakechain(
        string $body,
        string $signature,
        SigningPublicKey $publicKey
    ): array {
        $db = self::$easyDb;
        if ($db->inTransaction()) {
            $db->commit();
        }
        $db->beginTransaction();
        $lasthash = $db->row(
            'SELECT currhash, hashstate FROM chronicle_chain ORDER BY id DESC LIMIT 1'
        );

        // Instantiate the Blakechain.
        $blakechain = new Blakechain();
        if (empty($lasthash)) {
            $prevhash = '';
        } else {
            $prevhash = $lasthash['currhash'];
            $blakechain->setFirstPrevHash(
                Base64UrlSafe::decode($lasthash['currhash'])
            );
            $hashstate = Base64UrlSafe::decode($lasthash['hashstate']);
            $blakechain->setSummaryHashState($hashstate);
        }

        $currentTime = (new \DateTime())->format(\DateTime::ATOM);

        // Append data to the Blakechain:
        $blakechain->appendData(
            $currentTime .
            $publicKey->getString(true) .
            Base64UrlSafe::decode($signature) .
            $body
        );

        // Fields for insert:
        $fields = [
            'data' => $body,
            'prevhash' => $prevhash,
            'currhash' => $blakechain->getLastHash(),
            'hashstate' => $blakechain->getSummaryHashState(),
            'summaryhash' => $blakechain->getSummaryHash(),
            'publickey' => $publicKey->getString(),
            'signature' => $signature,
            'created' => $currentTime
        ];

        // Insert new row into the database:
        $db->insert('chronicle_chain', $fields);
        if (!$db->commit()) {
            $db->rollBack();
            throw new ChainAppendException('Could not commit new hash to database');
        }

        // This data is returned to the publisher:
        return [
            'currhash' => $fields['currhash'],
            'summaryhash' => $fields['summaryhash'],
            'created' => $currentTime
        ];
    }

    /**
     * Return a generic error response, timestamped and then signed by the
     * Chronicle server's public key.
     *
     * @param ResponseInterface $response
     * @param string $errorMessage
     * @param int $errorCode
     * @return ResponseInterface
     */
    public static function errorResponse(
        ResponseInterface $response,
        string $errorMessage,
        int $errorCode = 400
    ): ResponseInterface {
        return static::getSapient()->createSignedJsonResponse(
            $errorCode,
            [
                'version' => static::VERSION,
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'ERROR',
                'message' => $errorMessage
            ],
            self::getSigningKey(),
            $response->getHeaders(),
            $response->getProtocolVersion()
        );
    }

    /**
     * If we're using SQLite, we need a 1 or a 0.
     * Otherwise, TRUE/FALSE is fine.
     *
     * @param bool $value
     * @return bool|int
     */
    public static function getDatabaseBoolean(bool $value)
    {
        if (self::$easyDb->getDriver() === 'sqlite') {
            return $value ? 1 : 0;
        }
        return !empty($value);
    }

    /**
     * Given a clients Public ID, retrieve their Ed25519 public key.
     *
     * @param string $clientId
     * @return SigningPublicKey
     * @throws ClientNotFound
     */
    public static function getClientsPublicKey(string $clientId): SigningPublicKey
    {
        $sqlResult = static::$easyDb->row(
            "SELECT * FROM chronicle_clients WHERE publicid = ?",
            $clientId
        );
        if (empty($sqlResult)) {
            throw new ClientNotFound('Client not found');
        }
        return new SigningPublicKey(
            Base64UrlSafe::decode($sqlResult['publickey'])
        );
    }

    /**
     * Get the EasyDB object (used for database queries)
     *
     * @return EasyDB
     */
    public static function getDatabase(): EasyDB
    {
        return self::$easyDb;
    }

    /**
     * Return a Sapient object, with the Slim Framework adapter included.
     *
     * @return Sapient
     */
    public static function getSapient(): Sapient
    {
        return new Sapient(new Slim());
    }

    /**
     * @return array
     */
    public static function getSettings(): array
    {
        return self::$settings;
    }

    /**
     * This gets the server's signing key.
     *
     * We should audit all calls to this method.
     *
     * @return SigningSecretKey
     * @throws FilesystemException
     */
    public static function getSigningKey(): SigningSecretKey
    {
        if (self::$signingKey) {
            return self::$signingKey;
        }

        // Load the signing key:
        $keyFile = \file_get_contents(CHRONICLE_APP_ROOT . '/local/signing-secret.key');
        if (!\is_string($keyFile)) {
            throw new FilesystemException('Could not load key file');
        }
        return new SigningSecretKey(
            Base64UrlSafe::decode($keyFile)
        );
    }

    /**
     * Store the database object in the Chronicle class.
     *
     * @param EasyDB $db
     * @return EasyDB
     */
    public static function setDatabase(EasyDB $db): EasyDB
    {
        self::$easyDb = $db;
        return self::$easyDb;
    }

    /**
     * @param array $settings
     * @return void
     */
    public static function storeSettings(array $settings)
    {
        self::$settings = $settings;
    }

    /**
     * Optional feature: Reject old signed messages.
     *
     * @param RequestInterface $request
     * @param string $index
     * @return void
     *
     * @throws HTTPException
     * @throws SecurityViolation
     * @throws TimestampNotProvided
     */
    public static function validateTimestamps(
        RequestInterface $request,
        string $index = 'request-time'
    ) {
        if (empty(self::$settings['request-timeout'])) {
            return;
        }
        $body = (string) $request->getBody();
        if (empty($body)) {
            throw new HTTPException('No post body was provided', 406);
        }
        /** @var array $json */
        $json = \json_decode($body, true);
        if (!\is_array($json)) {
            throw new HTTPException('Invalid JSON message', 406);
        }
        if (empty($json[$index])) {
            throw new TimestampNotProvided('Parameter "' . $index . '" not provided.', 401);
        }
        $sent = new \DateTimeImmutable($json[$index]);
        $expires = $sent->add(
            \DateInterval::createFromDateString(
                self::$settings['request-timeout']
            )
        );

        if (new \DateTime('NOW') > $expires) {
            throw new SecurityViolation('Request timestamp is too old. Please resend.', 408);
        }

        /* Timestamp checks out. We don't throw anything. */
    }
}
