<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use App\Controller\HomeController;
use App\Controller\UrlController;
use App\Controller\CheckController;

session_start();

$container = new Container();
$container->set('view', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});
$container->set('connect', function () {
    return Connection::get()->connect();
});

$app = AppFactory::createFromContainer($container);
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->get('/', [HomeController::class, 'home'])
    ->setName('home');

$app->get('/urls', [UrlController::class, 'showUrls'])
    ->setName('urls');

$app->post('/urls', [UrlController::class, 'store'])
    ->setName('store');

$app->get('/urls/{id}', [UrlController::class, 'showUrl'])
    ->setName('url');

$app->post('/urls/{id:[0-9]+}/checks', [CheckController::class, 'check'])
    ->setName('check');

$app->run();
