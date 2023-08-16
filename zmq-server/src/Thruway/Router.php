<?php namespace App\Thruway;

use Thruway\Authentication\AuthenticationManager;
use Thruway\Authentication\WampCraAuthProvider;
use Thruway\Transport\RatchetTransportProvider;

/**
 * Class Router
 * @package App\Thruway
 */
class Router {

    /** @var \Thruway\Peer\Router */
    public $router;

    public function __construct() {
        $this->router = new \Thruway\Peer\Router();

        $this->router->registerModule(new AuthenticationManager());

        /*
         * Setup external access (Browser + PHP Subscribers)
         */

        // Auth
        $this->router->addInternalClient(new AuthProviderClient(['realm1']));
        // Socket
        $this->router->registerModule(new RatchetTransportProvider('0.0.0.0', 9100));


        /*
         * Setup internal access (PHP Publishers)
         */

        // Auth
        $auth = new WampCraAuthProvider(['realm1']);
        $this->router->addInternalClient($auth);
        $auth->setUserDb(new WampCraDB());
        // Socket
        $this->router->addInternalClient(new PHPClient('realm1', 9101, $this->router->getLoop()));


        /*
         * Setup monitoring client (Heartbeat)
         */
        $this->router->registerModule(new MonitoringClient());
    }

    public function run() {
        try {
            $this->router->start();
        } catch(\Exception $e) {
            echo $e->getMessage();
        }
    }

}
