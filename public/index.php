<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

Connection::get()->connect();
// try {
//     Connection::get()->connect();
//     echo 'A connection to the PostgreSQL database sever has been established successfully.';
// } catch (\PDOException $e) {
//     echo $e->getMessage();
// }

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'home.phtml');
})->setName('home');

$app->get('/urls', function ($request, $response) {
    return $this->get('renderer')->render($response, 'sites.phtml');
})->setName('urls');

$app->post('/urls', function ($request, $response) {
    $dataRequest = $request->getParsedBodyParam('url');
    $urlName = $dataRequest['name'];

    $errors = new Valitron\Validator($dataRequest);
    $errors->rule('required', 'name');
    if(!$errors->validate()) {
        $message = 'URL не должен быть пустым';
        $params = ['errors' => $errors, 'message' => $message];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $errors->rule('lengthMax', 'name', 255);
    if(!$errors->validate()) {
        $message = 'URL не должен превышать 255 символов';
        $params = ['errors' => $errors, 'message' => $message];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $errors->rule('url', 'name');
    if(!$errors->validate()) {
        $message = 'Некорректный URL';
        $params = ['errors' => $errors, 'message' => $message];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $params = ['url' => $dataRequest];

    
    

    // $errors->rule('required', 'url')->message('URL не должен быть пустым');
    // $errors->rule('lengthMax', 'url', 255)->message('Некорректный URL');
    // $errors->rule('url', 'url')->message('Некорректный URL');
    // if (!$errors->validate()) {
    //     print_r($errors->errors());
    // }



    // $params = ['url' => $dataRequest];
    // dd($dataRequest);
    return $this->get('renderer')->render($response->withStatus(422), 'sites.phtml', $params);
});

$app->get('/urls/{id}', function ($request, $response, array $args) {
    return $this->get('renderer')->render($response, 'show.phtml');
})->setName('check');

$app->post('/urls/{id}/check', function ($request, $response, array $args) {
    return $this->get('renderer')->render($response, 'show.phtml');
});

$app->run();