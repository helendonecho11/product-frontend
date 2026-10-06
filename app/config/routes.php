<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$router->get('/', 'Status::index');
$router->post('/api/auth/login', 'Auth::login');
$router->post('/api/auth/refresh', 'Auth::refresh');
$router->post('/api/auth/logout', 'Auth::logout');
$router->get('/api/auth/me', 'Auth::me');
$router->get('/api/products', 'Products::index');
$router->post('/api/products', 'Products::create');
$router->get('/api/products/{id}', 'Products::show');
$router->put('/api/products/{id}', 'Products::update');
$router->patch('/api/products/{id}', 'Products::update');
$router->delete('/api/products/{id}', 'Products::destroy');
