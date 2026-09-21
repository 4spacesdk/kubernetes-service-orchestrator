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

use AllowDynamicProperties;
use App\Entities\User;
use App\Helpers\Client;
use CodeIgniter\Controller;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Helpers\DebugLog;
use DebugTool\Data;
use Psr\Log\LoggerInterface;
use RestExtension\QueryParser;
use RestExtension\RestRequest;

/**
 * @property QueryParser $queryParser
 */
#[AllowDynamicProperties] abstract class BaseController extends Controller {

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
            // Not measured: the newrelic extension is not in the image and will not be, so
            // the guard above is false on every run and the call cannot be reached.
            // @codeCoverageIgnoreStart
            newrelic_ignore_transaction();
            // @codeCoverageIgnoreEnd
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
        $this->response->setJSON(DebugLog::ResponseBody());
        $this->response->send();
    }

    /**
     * The default was 200, so an error sent without a status came out as a success to
     * anything reading the status code - the envelope's `status: ERROR` is the only place
     * it showed. 400 here and in `ResourceController::error()`, which used to disagree with
     * it by defaulting to 503.
     */
    protected function fail($error, $code = 400) {
        Data::set('status', 'ERROR');
        if ($error) {
            Data::set('error', $error);
        }
        Data::set('bench', round(timer()->getElapsedTime('code-start'), 3));
//        Data::set('version', getVersion());
        $this->printResponse($code);
    }

    /**
     * An error response, sent the same way `success()` sends a successful one.
     *
     * This used to end with `exit`, which ended the PHP process. That worked - the body is
     * captured by CodeIgniter's output buffer either way - but it skipped the framework's
     * shutdown, so `post_system` never ran and RestExtension never wrote its access log
     * entry. Error responses were the ones missing from it.
     *
     * Because the process no longer dies here, **every caller has to return straight
     * after**. Otherwise the method carries on with whatever it was refusing to do, and a
     * second `success()` appends a second JSON document to the same body.
     */
    private function printResponse($code) {
        $this->response->setStatusCode($code);
        $this->response->setJSON(DebugLog::ResponseBody());
        $this->response->send();
    }

}
