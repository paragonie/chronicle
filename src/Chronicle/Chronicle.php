<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

use ParagonIE\Blakechain\Blakechain;
use ParagonIE\Chronicle\Exception\{
    BaseException,
    ChainAppendException,
    ClientNotFound,
    FilesystemException,
    HTTPException,
    InstanceNotFoundException,
    InvalidInstanceException,
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
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Chronicle
 * @package ParagonIE\Chronicle
 */
class Chronicle
{
    /** @var EasyDB $easyDb */
    protected static $easyDb;

    /** @var array<string, string> $settings */
    protected static $settings;

    /** @var SigningSecretKey $signingKey */
    protected static $signingKey;

    /** @var string $tablePrefix */
    protected static $tablePrefix = '';

    /* This constant is the name of the header used to find the
       corresponding public key: */
    const CLIENT_IDENTIFIER_HEADER = 'Chronicle-Client-Key-ID';

    /* This constant denotes the Chronicle version running, server-side */
    const VERSION = '1.1.x';

    /**
     * @param string $name
     * @return string
     * @throws InvalidInstanceException
     */
    public static function getTableName(string $name)
    {
        if (empty(self::$tablePrefix)) {
            return self::$easyDb->escapeIdentifier(
                'chronicle_' . $name
            );
        }
        if (self::$tablePrefix === 'replication') {
            throw new InvalidInstanceException(
                'The name "replication" is a reserved name.'
            );
        }
        return self::$easyDb->escapeIdentifier(
            'chronicle_' . self::$tablePrefix . '_' . $name
        );
    }

    /**
     * This extends the Blakechain with an arbitrary message, signature, and
     * public key.
     *
     * @param string $body
     * @param string $signature
     * @param SigningPublickey $publicKey
     * @return array<string, string>
     *
     * @throws BaseException
     * @throws \SodiumException
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
        /** @var array<string, string> $lasthash */
        $lasthash = $db->row(
            'SELECT currhash, hashstate 
             FROM ' . self::getTableName('chain') . ' 
             ORDER BY id DESC 
             LIMIT 1'
        );

        // Instantiate the Blakechain.
        $blakechain = new Blakechain();
        if (empty($lasthash)) {
            $prevhash = null;
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

        // Normalize data fields based on database type
        self::normalize($db->getDriver(), $fields);

        // Insert new row into the database:
        $db->insert(self::getTableName('chain'), $fields);
        if (!$db->commit()) {
            $db->rollBack();
            throw new ChainAppendException('Could not commit new hash to database');
        }

        // This data is returned to the publisher:
        return [
            'currhash' => (string) $fields['currhash'],
            'summaryhash' => (string) $fields['summaryhash'],
            'created' => (string) $currentTime
        ];
    }

    /**
     * Normalize the data before it goes to database, because every database
     * has its own system.
     *
     * @param string $databaseType
     * @param array &$data
     * @return void
     *
     */
    public static function normalize(string $databaseType, array &$data)
    {
        // Detect database type
        if (\strtolower($databaseType) === 'mysql') {
            // Ignore this; it will be set by the database system automatically.
            if (isset($data['created'])) {
                unset($data['created']);
            }
        }
        // We don't return anything here.
    }

    /**
     * Return a generic error response, timestamped and then signed by the
     * Chronicle server's public key.
     *
     * @param ResponseInterface $response
     * @param string $errorMessage
     * @param int $errorCode
     * @return ResponseInterface
     *
     * @throws FilesystemException
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
     * Given a clients Public ID, retrieve their Ed25519 public key.
     *
     * @param string $clientId
     * @param bool $adminOnly
     * @return SigningPublicKey
     *
     * @throws BaseException
     */
    public static function getClientsPublicKey(
        string $clientId,
        bool $adminOnly = false
    ): SigningPublicKey {
        if ($adminOnly) {
            /** @var array<string, string> $sqlResult */
            $sqlResult = static::$easyDb->row(
                "SELECT * FROM " . self::getTableName('clients') . " WHERE publicid = ? AND isAdmin",
                $clientId
            );
        } else {
            /** @var array<string, string> $sqlResult */
            $sqlResult = static::$easyDb->row(
                "SELECT * FROM " . self::getTableName('clients') . " WHERE publicid = ?",
                $clientId
            );
        }
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
     * @return array<string, string>
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
     *
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
     * @param array<string, string> $settings
     * @return void
     */
    public static function storeSettings(array $settings)
    {
        self::$settings = $settings;
    }

    /**
     * @param string $prefix
     * @return void
     *
     * @throws InstanceNotFoundException
     */
    public static function setTablePrefix(string $prefix)
    {
        /** @var array<string, string> $instances */
        $instances = self::$settings['instances'];
        if (!\in_array($prefix, $instances, true)) {
            throw new InstanceNotFoundException(
                'Instance ' . $prefix . ' not found in settings'
            );
        }
        self::$tablePrefix = $prefix;
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
        try {
            $sent = new \DateTimeImmutable((string)($json[$index]));
        } catch (\Exception $ex) {
            throw new SecurityViolation('Request timestamp is invalid. Please resend.', 408);
        }

        $expires = $sent->add(
            \DateInterval::createFromDateString(
                (string) self::$settings['request-timeout']
            )
        );

        if (new \DateTime('NOW') > $expires) {
            throw new SecurityViolation('Request timestamp is too old. Please resend.', 408);
        }

        /* Timestamp checks out. We don't throw anything. */
    }
}
