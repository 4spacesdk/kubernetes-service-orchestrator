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

/*
$routes->get('regex', function() {
    $regex = '/latest-minor/';
    $tag = 'latest-minor';

    if (preg_match($regex, $tag)) {
        Data::debug('cool', $regex, $tag);
    }

    $regex = '/[0-9]+.[0-9]+.[0-9]+$/';
    $tag = '3.3.0';

    if (preg_match($regex, $tag)) {
        Data::debug('cool', $regex, $tag);
    }

    Services::response()->setJSON(Data::getStore());
    Services::response()->send();
});
*/
