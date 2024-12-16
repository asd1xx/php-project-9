<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use App\Controller\HomeController;
use App\Controller\UrlController;
use App\Controller\CheckController;
use Psr\Http\Message\ServerRequestInterface as Request;

session_start();

$container = new Container();
$container->set('connect', function () {
    return Connection::get()->connect();
});
$container->set('view', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addRoutingMiddleware();

$customErrorHandler = function (Request $request, Throwable $exception) use ($app, $container) {
    $response = $app->getResponseFactory()->createResponse();

    if ($exception->getCode() == 404) {
        return $container->get('view')
            ->render($response, '404.phtml')
            ->withStatus(404);
    }

    return $response;
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->get('/', [HomeController::class, 'home'])
    ->setName('home');

$app->get('/urls', [UrlController::class, 'showUrls'])
    ->setName('urls');

$app->post('/urls', [UrlController::class, 'store'])
    ->setName('store');

$app->get('/urls/{id:[0-9]+}', [UrlController::class, 'showUrl'])
    ->setName('url');

$app->post('/urls/{id:[0-9]+}/checks', [CheckController::class, 'check'])
    ->setName('check');

$app->run();
