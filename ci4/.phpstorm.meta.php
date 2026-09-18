<?php namespace PHPSTORM_META;

/**
 * Tells PhpStorm what `service('name')` returns. The helper is typed `?object`, so without
 * this every call is untyped. Add a line when a service is added to Config\Services.
 *
 * The map is written out in each override: PhpStorm does not read variables here.
 */
override(\service(0), map([
    'integrations' => \App\Libraries\Integrations\IntegrationFactory::class,
    'commands' => \CodeIgniter\CLI\Commands::class,
    'encrypter' => \CodeIgniter\Encryption\EncrypterInterface::class,
    'logger' => \CodeIgniter\Log\Logger::class,
    'request' => \CodeIgniter\HTTP\IncomingRequest::class,
    'response' => \CodeIgniter\HTTP\ResponseInterface::class,
    'routes' => \CodeIgniter\Router\RouteCollection::class,
    'superglobals' => \CodeIgniter\Superglobals::class,
    'toolbar' => \CodeIgniter\Debug\Toolbar::class,
]));

override(\single_service(0), map([
    'integrations' => \App\Libraries\Integrations\IntegrationFactory::class,
    'commands' => \CodeIgniter\CLI\Commands::class,
    'encrypter' => \CodeIgniter\Encryption\EncrypterInterface::class,
    'logger' => \CodeIgniter\Log\Logger::class,
    'request' => \CodeIgniter\HTTP\IncomingRequest::class,
    'response' => \CodeIgniter\HTTP\ResponseInterface::class,
    'routes' => \CodeIgniter\Router\RouteCollection::class,
    'superglobals' => \CodeIgniter\Superglobals::class,
    'toolbar' => \CodeIgniter\Debug\Toolbar::class,
]));
