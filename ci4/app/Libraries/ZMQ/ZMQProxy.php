<?php namespace App\Libraries\ZMQ;

class ZMQProxy {

    /** @var ZMQProxy */
    private static $instance;

    public static function getInstance(): ZMQProxy {
        if (!self::$instance) {
            self::$instance = new ZMQProxy();
            self::$instance->connect();
        }
        return self::$instance;
    }

    /** @var \ZMQContext */
    private $context;

    /** @var \ZMQSocket */
    private $socket;

    private function connect() {
        if (!$zmqHost = getenv('ZMQ_HOST')) {
//            throw new \Exception("ZMQProxy, Environment variable is missing \"ZMQHost\"");
            return;
        }
        if (!$zmqPort = getenv('ZMQ_INTERNAL_PORT')) {
//            throw new \Exception("ZMQProxy, Environment variable is missing \"ZMQPort\"");
            return;
        }

        $this->context = new \ZMQContext();
        try {
            $this->socket = $this->context->getSocket(\ZMQ::SOCKET_PUSH);
            $this->socket->connect("tcp://{$zmqHost}:{$zmqPort}");
        } catch(\ZMQSocketException $e) {
            \DebugTool\Data::debug($e->getMessage());
        }
    }

    public function send(string $event, array $data) {
        if (!isset($this->socket)) {
            return;
        }
        try {
            $this->socket->send(json_encode(
                [
                    'event' => $event,
                    'data' => $data,
                ]
            ));
        } catch(\ZMQSocketException $e) {
            \DebugTool\Data::debug($e->getMessage());
        }
    }

}
