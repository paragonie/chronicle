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
    /** @var \Slim\App $self */
    $self = $this;

    // Admin only:
    $self->group('', function () {
        /** @var \Slim\App $self */
        $self = $this;
        $self->post('/register', Register::class);
        $self->post('/revoke', Revoke::class);
    })->add(CheckAdminSignature::class);

    // Clients:
    $self->post('/publish', Publish::class)
        ->add(CheckClientSignature::class);

    // Public:
    $self->get('/lasthash', 'lookup.lasthash');
    $self->get('/lookup/{hash}', 'lookup.hash');
    $self->get('/since/{hash}', 'lookup.since');
    $self->get('/export', 'lookup.export');
    $self->get('/replica/{source}/lasthash', 'replica.lasthash');
    $self->get('/replica/{source}/lookup/{hash}', 'replica.hash');
    $self->get('/replica/{source}/since/{hash}', 'replica.since');
    $self->get('/replica/{source}/export', 'replica.export');
    $self->get('/replica/{source}', 'replica.subindex');
    $self->get('/replica', 'replica.index');
    $self->get('/', Index::class);
    $self->get('', Index::class);
});

$app->get('/', function (RequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface {
    /** @var \Slim\App|\Slim\Container $self */
    $self = $this;
    /* UX enhancement: Automatically redirect to chronicle URI if client header is present: */
    if ($request instanceof \Slim\Http\Request) {
        if ($request->hasHeader(Chronicle::CLIENT_IDENTIFIER_HEADER)) {
            \header("Location: /chronicle");
            exit;
        }
    }
    // Render index view
    if ($self instanceof \Slim\Container) {
        /** @var \Slim\Container $c */
        $c = $self;
    } else {
        /** @var \Slim\Container $c */
        $c = $self->getContainer();
    }
    /** @var \Slim\Views\PhpRenderer $renderer */
    $renderer = $c->get('renderer');
    return $renderer->render($response, 'index.phtml', $args);
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
$container['replica.subindex'] = function () {
    return new Replica('subindex');
};
