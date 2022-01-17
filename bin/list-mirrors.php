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
 * The only parameter supported is the instance.
 */
$getopt = new Getopt([
    new Option('i', 'instance', Getopt::OPTIONAL_ARGUMENT),
]);
$getopt->process();

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

$isSQLite = strpos($settings['database']['dsn'] ?? '', 'sqlite:') !== false;

$db->beginTransaction();
/** @var array<array<string, string|int|bool>> $mirrors */
$mirrors = $db->run(
    "SELECT * FROM " .
        Chronicle::getTableNameUnquoted('clients', $isSQLite) .
    " ORDER BY sortpriority ASC"
);

echo json_encode([
    'count' => count($mirrors),
    'mirrors' => $mirrors
], JSON_PRETTY_PRINT), PHP_EOL;
exit(0);
