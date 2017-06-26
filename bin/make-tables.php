<?php
$root = \dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/settings.php';

/** @var array $settings */
$app = new \Slim\App($settings);

if (!isset($app)) {
    throw new Error('Variable $app is not defined');
}
if (!($app instanceof \Slim\App)) {
    throw new Error('Variable $app is not an App');
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
    echo \json_encode(
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
    $scripts[] = \file_get_contents($file);
}

$db->beginTransaction();
foreach ($scripts as $script) {
    $db->query($script);
}
if ($db->commit()) {
    echo 'Tables created successfully!', PHP_EOL;
} else {
    $db->rollBack();
    echo $db->errorInfo()[0], PHP_EOL;
    exit(1);
}
