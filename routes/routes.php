<?php

/** @var Bramus\Router\Router $router */

// Define routes here
$router->setBasePath('/catering_api');
$router->get('/test', App\Controllers\IndexController::class . '@test');
$router->get('/', App\Controllers\IndexController::class . '@test');
$router->get('/facilities', App\Controllers\FacilityController::class . '@getFacilities');
$router->post('/facilities', App\Controllers\FacilityController::class . '@createFacility');
$router->get('/facilities/{facilityName}', App\Controllers\FacilityController::class . '@getFacilityByName');
$router->put('/facilities/{facilityName}', App\Controllers\FacilityController::class . '@updateFacility');
$router->delete('/facilities/{facilityName}', App\Controllers\FacilityController::class . '@deleteFacility');
