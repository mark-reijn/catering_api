<?php

/** @var Bramus\Router\Router $router */

// Define routes here
$router->setBasePath('/catering_api');
$router->get('/test', App\Controllers\IndexController::class . '@test');
$router->get('/', App\Controllers\IndexController::class . '@test');
