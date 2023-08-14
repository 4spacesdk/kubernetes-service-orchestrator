<?php

namespace App\Core;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 *
 * @package CodeIgniter
 */

use App\Entities\User;
use App\Helpers\Client;
use CodeIgniter\Controller;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use DebugTool\Data;
use Psr\Log\LoggerInterface;
use RestExtension\QueryParser;
use RestExtension\RestRequest;

/**
 * @property QueryParser $queryParser
 */
abstract class BaseController extends Controller {

    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = [];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        timer('code-start');

        $this->queryParser = new QueryParser();
        $this->queryParser->parseRequest($request);

        Client::Init();

        Events::on('post_controller_constructor', [$this, 'postInitController']);
    }

    public function postInitController() {
        if (ENVIRONMENT != 'production' && extension_loaded('newrelic')) {
            newrelic_ignore_transaction();
        }

        if (RestRequest::getInstance()->userId) {
            Client::SetUser(new User((array)RestRequest::getInstance()->userData));
            Client::SetToken(RestRequest::getInstance()->token);
        }
    }

    abstract public function requireAuth(string $method): bool;

    public function success() {
        Data::set('status', 'OK');
        Data::set('bench', round(timer()->getElapsedTime('code-start'), 3));
//        Data::set('version', getVersion());
        $this->response->setJSON(Data::getStore());
        $this->response->send();
    }

    protected function fail($error, $code = 200) {
        Data::set('status', 'ERROR');
        if ($error) {
            Data::set('error', $error);
        }
        Data::set('bench', round(timer()->getElapsedTime('code-start'), 3));
//        Data::set('version', getVersion());
        $this->printResponse($code);
    }

    private function printResponse($code) {
        $this->response->setStatusCode($code);
        $this->response->setJSON(Data::getStore());
        $this->response->send();
        exit;
    }

}
