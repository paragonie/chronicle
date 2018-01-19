<?php
declare(strict_types=1);

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/src/settings.php';

/**
 * @global array $settings
 * @global \Slim\App $app
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
        $settings = \json_decode($settingsFile, true);
    }
} else {
    echo 'Please run install.php first.', PHP_EOL;
    exit(1);
}

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

$db = ParagonIE\EasyDB\Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? null,
    $settings['database']['password'] ?? null,
    $settings['database']['options'] ?? []
);

$scripts = [];
foreach (\glob($root . '/sql/' . $db->getDriver() . '/*.sql') as $file) {
    echo $file . PHP_EOL;
    $scripts[] = \file_get_contents($file);
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
    echo $db->errorInfo()[0], PHP_EOL;
    exit(1);
}
