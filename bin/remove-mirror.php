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
 */
$getopt = new Getopt([
    new Option('m', 'mirror-id', Getopt::REQUIRED_ARGUMENT),
    new Option('i', 'instance', Getopt::OPTIONAL_ARGUMENT),
]);

$getopt->process();

$mirrorId = (int) $getopt->getOption('mirror-id');
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
    'php remove-mirror.php -m [id]', PHP_EOL, PHP_EOL,
    'If you need an instance id, use list-mirrors.php first.', PHP_EOL, PHP_EOL;
    exit(1);
}

$isSQLite = strpos($settings['database']['dsn'] ?? '', 'sqlite:') !== false;

$db->beginTransaction();
if (!$db->exists(
    "SELECT count(*) FROM " .
    Chronicle::getTableNameUnquoted('mirrors', $isSQLite) .
    " WHERE id = ?",
    $mirrorId
)) {
    $db->rollBack();
    echo 'Instance ' . $mirrorId . ' not found.', PHP_EOL;
    exit(1);
}

$db->delete(
    Chronicle::getTableNameUnquoted('mirrors', $isSQLite),
    [
        'id' => $mirrorId
    ]
);
if (!$db->commit()) {
    $db->rollBack();
    /** @var array<int, string> $errorInfo */
    $errorInfo = $db->errorInfo();
    echo $errorInfo[0], PHP_EOL;
    exit(1);
}
echo 'Mirror deleted successfully!', PHP_EOL;
