<?php namespace App\Thruway;

use React\EventLoop\LoopInterface;
use Thruway\Logging\Logger;
use Thruway\Module\RouterModuleInterface;
use Thruway\Peer\Client;
use Thruway\Peer\Router;
use Thruway\Peer\RouterInterface;

class MonitoringClient extends Client implements RouterModuleInterface {

    protected RouterInterface $_router;
    private int $clientCounter = 0;
    private array $sessionMeta = [];

    /**
     * Contructor
     */
    public function __construct() {
        parent::__construct("realm1");
    }

    /**
     * @param RouterInterface $router
     * @param LoopInterface $loop
     */
    public function initModule(RouterInterface $router, LoopInterface $loop) {
        $this->_router = $router;
        $this->setLoop($loop);
        $this->_router->addInternalClient($this);
    }

    /**
     * @param \Thruway\ClientSession $session
     * @param \Thruway\Transport\TransportInterface $transport
     */
    public function onSessionStart($session, $transport) {
        echo "--------------- Hello from MonitoringClient ------------\n";
        $session->subscribe('wamp.metaevent.session.on_join', function($args, $kwArgs, $options) {
            $this->clientCounter++;
            $sessionId = $args[0]->session;
            $this->sessionMeta[$sessionId] = [];
            Logger::debug($this, "Client #{$sessionId} joined. {$this->clientCounter} connected clients");
//            Logger::debug($this, json_encode($args, JSON_PRETTY_PRINT));
        });

        $session->subscribe('wamp.metaevent.session.on_leave', function($args, $kwArgs, $options) {
            $this->clientCounter--;
            $sessionId = $args[0]->session;
            $name = $this->sessionMeta[$sessionId]['name'] ?? '';
            unset($this->sessionMeta[$sessionId]);
            Logger::debug($this, "Client #{$sessionId} ({$name}) left. {$this->clientCounter} connected clients");
//            Logger::debug($this, json_encode($args, JSON_PRETTY_PRINT));
        });

        $session->subscribe('i.am', function($args, $kwArgs, $options) use ($transport) {
            $data = $args[0];
            $this->sessionMeta[$options->publisher]['name'] = $data->name;
            Logger::debug($this, "Client #{$options->publisher} is {$data->name}");
        }, ['disclose_publisher' => true]);

        $session->subscribe('heartbeat', function($args, $kwArgs, $options) use ($transport) {
            $name = $this->sessionMeta[$options->publisher]['name'] ?? '';
            Logger::debug($this, "Client #{$options->publisher} ({$name}) has a heartbeat");
        }, ['disclose_publisher' => true]);
    }

    /**
     * @return \Thruway\Router
     */
    public function getRouter() {
        return $this->_router;
    }

    public static function getSubscribedEvents() {
        return [];
    }

}
