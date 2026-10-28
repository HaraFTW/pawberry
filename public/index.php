<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use PawBerry\Core\Request;
use PawBerry\Core\Router;
use PawBerry\Core\Session;

Session::start();

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';

$request = Request::fromGlobals();
$response = $router->dispatch($request);
$response->send();
