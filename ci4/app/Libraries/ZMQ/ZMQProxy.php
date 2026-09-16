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

    /**
     * How long to wait for queued messages when the process shuts down, in milliseconds.
     *
     * Without this, ZeroMQ waits forever. A PUSH socket accepts a connect() and a send()
     * even when nothing is listening: the message is queued instead of failing, and the
     * context then blocks on shutdown until it has been delivered. The migration job runs
     * `php spark migrate` instead of the image entrypoint, so the zmq server never starts
     * in that pod and nothing is listening on 9101. A migration that pushes an event, such
     * as AddWorkspaceStatus calling Workspace::checkStatus(), therefore printed
     * "Migrations complete." and then hung until activeDeadlineSeconds killed the job,
     * six hours later, with the wait-for-migration init container blocking the rollout the
     * whole time.
     *
     * These events are UI notifications, so a bounded wait is the right trade. Half a
     * second is long enough to flush to a live server, and the worst case is half a
     * second rather than forever.
     */
    private const SHUTDOWN_LINGER_MS = 500;

    private function connect() {
        $zmqHost = 'localhost';
        $zmqPort = 9101;

        $this->context = new \ZMQContext();
        try {
            $this->socket = $this->context->getSocket(\ZMQ::SOCKET_PUSH);
            $this->socket->setSockOpt(\ZMQ::SOCKOPT_LINGER, self::SHUTDOWN_LINGER_MS);
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
