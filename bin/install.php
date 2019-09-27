<?php
declare(strict_types=1);

use GetOpt\{
    GetOpt,
    Option
};
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;

/** @var string $root */
$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

// Generate a signing key.
/** @var SigningSecretKey $signingKey */
$signingKey = SigningSecretKey::generate();

// Store the signing key:
\file_put_contents(
    $root . '/local/signing-secret.key',
    $signingKey->getString()
);

/**
 * @var Getopt $getopt
 *
 * This defines the Command Line options.
 *
 * These are many examples:
 *     php install.php
 *     php install.php --mysql
 *     php install.php --pgsql
 *     php install.php --sqlite
 *     php install.php --mysql --host localhost --port 3306 --username mysql_user --password mysql_password
 *     php install.php --pgsql --host=localhost --port=5432 --username=pgsql_user --password=pgsql_password
 *     php install.php --mysql --h localhost --port 3306 --u mysql_user --p mysql_password
 *     php install.php --pgsql --h=localhost --port=5432 --u=pgsql_user --p=pgsql_password
 *     php install.php --sqlite --database chronicle
 *     php install.php --sqlite --database=chronicle --extension db
 */
$getopt = new Getopt([
    new Option(null, 'mysql', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'pgsql', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'sqlite', Getopt::OPTIONAL_ARGUMENT),
    new Option('h', 'host', Getopt::OPTIONAL_ARGUMENT),
    new Option(null, 'port', Getopt::OPTIONAL_ARGUMENT),
    new Option('d', 'database', Getopt::OPTIONAL_ARGUMENT),
    new Option('e', 'extension', Getopt::OPTIONAL_ARGUMENT),
    new Option('u', 'username', Getopt::OPTIONAL_ARGUMENT),
    new Option('p', 'password', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->process();

/** @var string $mysql */
$mysql = $getopt->getOption('mysql') ?? false;
/** @var string $pgsql */
$pgsql = $getopt->getOption('pgsql') ?? false;
/** @var string $sqlite */
$sqlite = $getopt->getOption('sqlite') ?? (!$mysql && !$pgsql);
/** @var string $host */
$host = $getopt->getOption('host') ?? 'localhost';
/** @var string $port */
$port = $getopt->getOption('port') ?? ($mysql ? '3306' : ($pgsql ? '5432' : ''));
/** @var string $database */
$database = $getopt->getOption('database') ?? 'chronicle';
/** @var string $extension */
$extension = $getopt->getOption('extension') ?? 'db';
/** @var string $username */
$username = $getopt->getOption('username') ?? ($mysql ? 'mysqluser' : ($pgsql ? 'pgsqluser' : ''));
/** @var string $password */
$password = $getopt->getOption('password') ?? '';

// default SQLite
$databaseConfig = [
    'dsn' => 'sqlite:' . $root . '/local/' . $database . '.' . $extension,
];

if(!$sqlite){

    $dbType = $mysql ? 'mysql' : 'pgsql';

    $databaseConfig = [
        'dsn' => $dbType . ':host=' . $host . ';port=' . $port . ';dbname=' . $database,
        'username' => $username,
        'password' => $password,
    ];
}

// Write the default settings to the local settings file.
$localSettings = [
    'database' => $databaseConfig,
    // Map 'channel-name' => 'table_prefix'
    'instances' => [
        '' => ''
    ],
    'paginate-export' => null,
    'publish-new-clients' => true,
    'publish-revoked-clients' => true,
    // The maximum window of opportunity for replay attacks:
    'request-timeout' => '10 minutes',
    'scheduled-attestation' => '7 days',
    'signing-public-key' => $signingKey->getPublicKey()->getString()
];

\file_put_contents(
    $root . '/local/settings.json',
    \json_encode($localSettings, JSON_PRETTY_PRINT)
);
