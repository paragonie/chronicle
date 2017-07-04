<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\CliTests;

use GuzzleHttp\Client;
use ParagonIE\Chronicle\Chronicle;
use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\Factory;
use ParagonIE\Sapient\Adapter\Guzzle;
use ParagonIE\Sapient\Sapient;
use tflori\Getopt\{
    Getopt,
    Option
};

require_once dirname(dirname(__DIR__)) . '/cli-autoload.php';


if (!\is_readable(CHRONICLE_APP_ROOT . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

$settings = \json_decode(
    (string) \file_get_contents(CHRONICLE_APP_ROOT . '/local/settings.json'),
    true
);

/** @var EasyDB $db */
$db = Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);
Chronicle::setDatabase($db);

/**
 * @var Getopt
 *
 * This defines the Command Line options.
 */
$getopt = new Getopt([
    new Option(null, 'base-url', Getopt::REQUIRED_ARGUMENT)
]);
$getopt->parse();
$baseUrl = $getopt->getOption('base-url') ?? 'http://localhost:8080';

$http = new Client();
$sapient = new Sapient(new Guzzle($http));
$serverPublicKey = Chronicle::getSigningKey()->getPublickey();
