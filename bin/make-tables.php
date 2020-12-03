<?php
declare(strict_types=1);

use GetOpt\{
    GetOpt,
    Option
};
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\InstanceNotFoundException;

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/src/settings.php';

/**
 * @var array $settings
 * @var \Slim\App $app
 */
$app = new \Slim\App($settings);

if (!isset($app)) {
    throw new Error('Variable $app is not defined');
}

/* Local settings; not checked into git. */
$settings = [];
if (\is_readable($root . '/local/settings.json')) {
    $settingsFile = \file_get_contents($root . '/local/settings.json');
    if (\is_string($settingsFile)) {
        /** @var array<string, string> $settings */
        $settings = \json_decode($settingsFile, true);
    }
} else {
    echo 'Please run install.php first.', PHP_EOL;
    exit(1);
}
Chronicle::storeSettings($settings);

if (empty($settings['database'])) {
    echo "Please defined a database in local/settings.json. For example:\n\n";
    echo (string) \json_encode(
        [
            'database' => [
                'dsn' => 'pgsql:rest-of-dsn-goes-here',
                'username' => null,
                'password' => null,
                'options' => []
            ]
        ],
        JSON_PRETTY_PRINT
    );
    exit(1);
}

/** @var \ParagonIE\EasyDB\EasyDB $db */
$db = ParagonIE\EasyDB\Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? null,
    $settings['database']['password'] ?? null,
    $settings['database']['options'] ?? []
);

Chronicle::setDatabase($db);

/**
 * @var GetOpt $getopt
 *
 * This defines the Command Line options.
 */
$getopt = new GetOpt([
    new Option('i', 'instance', GetOpt::OPTIONAL_ARGUMENT),
]);
$getopt->process();


/** @var string $instance */
$instance = $getopt->getOption('instance') ?? '';

try {
    if (!empty($instance)) {
        /** @var array<string, string> $instances */
        $instances = $settings['instances'];
        if (!\array_key_exists($instance, $instances)) {
            throw new InstanceNotFoundException(
                'Instance ' . $instance . ' not found'
            );
        }
        Chronicle::setTablePrefix($instances[$instance]);
    }
} catch (InstanceNotFoundException $ex) {
    echo $ex->getMessage(), PHP_EOL;
    exit(1);
}

$scripts = [];
foreach (\glob($root . '/sql/' . $db->getDriver() . '/*.sql') as $file) {
    echo $file . PHP_EOL;
    /** @var string $contents */
    $contents =  \file_get_contents($file);

    // Process the table name
    $contents = \preg_replace_callback(
        '#chronicle_([A-Za-z0-9_]+)#',
        /**
         * @param array<int, string> $matches
         * @return string
         */
        function ($matches) {
            return \str_replace('"', '', Chronicle::getTableName($matches[1]));
        },
        $contents
    );
    $scripts[] = $contents;
}

$db->beginTransaction();
foreach ($scripts as $script) {
    foreach (explode(';', $script) as $piece) {
        $piece = trim($piece);
        if (empty($piece)) {
            continue;
        }
        $db->query($piece);
    }
}
if ($db->commit()) {
    echo 'Tables created successfully!', PHP_EOL;
} else {
    $db->rollBack();
    /** @var array<int, string> $errorInfo */
    $errorInfo = $db->errorInfo();
    echo $errorInfo[0], PHP_EOL;
    exit(1);
}
