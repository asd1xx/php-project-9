<?php

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use DI\Container;

class HomeController
{
    public function __construct(private Container $container)
    {
    }

    public function home(Request $request, Response $response)
    {
        return $this->container->get('view')->render($response, 'home.phtml');
    }
}
