<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;
use DebugTool\Data;

/**
 * @var RouteCollection $routes
 */

$routes->get('/', 'Home::index');

$routes->cli('jobby/run/(:segment)', 'Jobby::run/$1');
$routes->cli('ZMQ/migrationJobChangedStatus', 'ZMQ::migrationJobChangedStatus');
$routes->cli('ZMQ/workspaceCreated', 'ZMQ::workspaceCreated');
$routes->cli('ZMQ/workspaceDeleted', 'ZMQ::workspaceDeleted');

$routes->get('connect/check', function() {
    \AuthExtension\OAuth2\Check::handle(Services::response());
});
$routes->post('connect/token', function() {
    \AuthExtension\OAuth2\Token::handle(Services::response());
});
