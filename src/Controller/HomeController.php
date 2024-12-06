<?php

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;

class HomeController
{
    private mixed $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function home(Request $request, Response $response)
    {
        return $this->container->get('view')->render($response, 'home.phtml');
    }
}
