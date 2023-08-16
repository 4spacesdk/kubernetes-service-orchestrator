<?php namespace App\Thruway;

use React\EventLoop\LoopInterface;
use React\ZMQ\Context;
use Thruway\ClientSession;
use Thruway\Logging\Logger;
use Thruway\Peer\Client;
use ZMQ;

/**
 * Class Wamp2Server
 * @package App\Thruway
 */
class PHPClient extends Client {

    private string $port;

    public function __construct(string $realm, string $port, LoopInterface $loop = null) {
        $this->port = $port;
        parent::__construct($realm, $loop);
    }

    public function onSessionStart($session, $transport) {
        parent::onSessionStart($session, $transport);

        // Listen for the web server to make a ZeroMQ push after an ajax request
        $context = new Context($this->getLoop());
        $pull = $context->getSocket(ZMQ::SOCKET_PULL);
        $pull->bind("tcp://0.0.0.0:{$this->port}");
        $pull->on('message', function($data) use ($session) {
            $this->onResource($session, $data);
        });
    }

    public function onResource(ClientSession $session, $payload) {
        /** @var OnResourceData $data */
        $data = json_decode($payload);
        if (!$data) {
            Logger::warning($this, 'Invalid json payload:');
            Logger::info($this, $payload);
            return;
        }
        $data->identifier = uniqid();
        $path = strtolower($data->event); // Topics must be lowercase

        try {
            Logger::info($this, 'Publish to: '.$path);
            $session->publish($path, [json_encode($data)]);
        } catch (\Exception $e) {
            Logger::notice($this, $e->getMessage().get_class($e));
        }
    }

}
