<?php
declare(strict_types=1);

use Cache\Adapter\Memcached\MemcachedCachePool;
use Slim\Container;
// DIC configuration

if (!isset($app)) {
    throw new Error('Variable $app is not defined');
}
if (!($app instanceof \Slim\App)) {
    throw new Error('Variable $app is not an App');
}

/** @var Container $container */
$container = $app->getContainer();

// view renderer
$container['renderer'] = function (Container $c): \Slim\Views\PhpRenderer {
    /** @var array<string, array> $cset */
    $cset = $c->get('settings');
    /** @var array<string, string> $settings */
    $settings = $cset['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function (Container $c): \Monolog\Logger {

    /** @var array<string, array> $cset */
    $cset = $c->get('settings');
    /** @var array<string, string> $settings */
    $settings = $cset['logger'];
    $logger = new Monolog\Logger((string)$settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(
        new Monolog\Handler\StreamHandler(
            $settings['path'],
            (int) $settings['level']
        )
    );
    return $logger;
};

// easydb
$container['database'] = function (Container $c): \ParagonIE\EasyDB\EasyDB {
    return \ParagonIE\Chronicle\Chronicle::getDatabase();
};
