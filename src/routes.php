<?php
declare(strict_types=1);

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Handlers\{
    Index,
    Lookup,
    Publish,
    Register,
    Replica,
    Revoke
};
use ParagonIE\Chronicle\Middleware\{
    CheckAdminSignature,
    CheckClientSignature
};
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

if (!isset($app)) {
    throw new Error('Variable $app is not defined');
}
if (!($app instanceof \Slim\App)) {
    throw new Error('Variable $app is not an App');
}
if (!isset($container)) {
    throw new Error('Variable $container is not defined');
}
if (!($container instanceof \Slim\Container)) {
    throw new Error('Variable $app is not a Container');
}

// Routes
$app->group('/chronicle', function () {

    // Admin only:
    $this->group('', function () {
        $this->post('/register', Register::class);
        $this->post('/revoke', Revoke::class);
    })->add(CheckAdminSignature::class);

    // Clients:
    $this->post('/publish', Publish::class)
        ->add(CheckClientSignature::class);

    // Public:
    $this->get('/lasthash', 'lookup.lasthash');
    $this->get('/lookup/{hash}', 'lookup.hash');
    $this->get('/since/{hash}', 'lookup.since');
    $this->get('/export', 'lookup.export');
    $this->get('/replica/{source}/lasthash', 'replica.lasthash');
    $this->get('/replica/{source}/lookup/{hash}', 'replica.hash');
    $this->get('/replica/{source}/since/{hash}', 'replica.since');
    $this->get('/replica/{source}/export', 'replica.export');
    $this->get('/replica', 'replica.index');
    $this->get('/', Index::class);
    $this->get('', Index::class);
});

$app->get('/', function (RequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface {
    /* UX enhancement: Automatically redirect to chronicle URI if client header is present: */
    if ($request instanceof \Slim\Http\Request && $response instanceof \Slim\Http\Response) {
        if ($request->hasHeader(Chronicle::CLIENT_IDENTIFIER_HEADER)) {
            \header("Location: /chronicle");
            exit;
        }
    }
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$container[CheckClientSignature::class] = function () {
    return new CheckClientSignature();
};
$container[CheckAdminSignature::class] = function () {
    return new CheckAdminSignature();
};
$container[Register::class] = function () {
    return new Register();
};
$container[Revoke::class] = function () {
    return new Revoke();
};
$container[Publish::class] = function () {
    return new Publish();
};
$container['lookup.lasthash'] = function () {
    return new Lookup('lasthash');
};
$container['lookup.hash'] = function () {
    return new Lookup('hash');
};
$container['lookup.since'] = function () {
    return new Lookup('since');
};
$container['lookup.export'] = function () {
    return new Lookup('export');
};
$container['replica.lasthash'] = function () {
    return new Replica('lasthash');
};
$container['replica.hash'] = function () {
    return new Replica('hash');
};
$container['replica.since'] = function () {
    return new Replica('since');
};
$container['replica.export'] = function () {
    return new Replica('export');
};
$container['replica.index'] = function () {
    return new Replica('index');
};
