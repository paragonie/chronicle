<?php
declare(strict_types=1);

use GetOpt\{
    GetOpt,
    Option
};
use ParagonIE\EasyDB\Factory;
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\InstanceNotFoundException;

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

if (!\is_readable($root . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

/** @var array $settings */
$settings = \json_decode(
    (string) \file_get_contents($root . '/local/settings.json'),
    true
);

$db = Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);

// Pass database instance to Chronicle
Chronicle::setDatabase($db);

/**
 * This defines the Command Line options.
 *
 * These two are equivalent:
 *     php add-mirror.php -u chronicle.example.com -p foo
 *     php add-mirror.php --url chronicle.example.com --publickey=foo
 */
$getopt = new Getopt([
    new Option('p', 'publickey', Getopt::REQUIRED_ARGUMENT),
    new Option('u', 'url', Getopt::REQUIRED_ARGUMENT),
    new Option('c', 'comment', Getopt::OPTIONAL_ARGUMENT),
    new Option('s', 'sort', Getopt::OPTIONAL_ARGUMENT),
    new Option('i', 'instance', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->process();

/** @var string $url */
$url = $getopt->getOption('url');
/** @var string $publicKey */
$publicKey = $getopt->getOption('publickey');
/** @var string|null $comment */
$comment = $getopt->getOption('comment');
$sort = (int) ($getopt->getOption('sort') ?? 0);
$instance = (string) $getopt->getOption('instance');

try {
    if (!empty($instance)) {
        if (!\array_key_exists($instance, $settings['instances'])) {
            throw new InstanceNotFoundException(
                'Instance ' . $instance . ' not found'
            );
        }
        Chronicle::setTablePrefix($settings['instances'][$instance]);
    }
} catch (InstanceNotFoundException $ex) {
    echo $ex->getMessage(), PHP_EOL;
    exit(1);
}

if (empty($publicKey)) {
    echo 'Usage:', PHP_EOL, "\t",
    'php add-mirror.php -u url -p publickeygoeshere [-c comment]', PHP_EOL, PHP_EOL;
    exit(1);
}

$isSQLite = strpos($settings['database']['dsn'] ?? '', 'sqlite:') !== false;

$db->beginTransaction();
$db->insert(
    Chronicle::getTableNameUnquoted('mirrors', $isSQLite),
    [
        'url' => $url,
        'publickey' => $publicKey,
        'comment' => $comment,
        'sortpriority' => $sort
    ]
);
if (!$db->commit()) {
    $db->rollBack();
    /** @var array<int, string> $errorInfo */
    $errorInfo = $db->errorInfo();
    echo $errorInfo[0], PHP_EOL;
    exit(1);
}
echo 'Mirror added successfully!', PHP_EOL;
