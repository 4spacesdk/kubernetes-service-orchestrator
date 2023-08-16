<?php namespace App;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Vinelab\Minion\Client;

class Subscriber {

    private Client $internalClient;

    public function __construct() {
        $internalHost = 'localhost';

        $loop = Factory::create();

        $this->setupInternalClient($internalHost, $loop);

        $loop->addPeriodicTimer(300, fn() => $this->heartbeat());

        $loop->run();
    }

    private function subscribe(Client $client) {
        $client->subscribe(Events::MigrationJob_Changed_Status(0), function ($payload) {
            log_('MigrationJob_Changed_Status');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ migrationJobChangedStatus --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::Workspace_Created(), function ($payload) {
            log_('Workspace_Created');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ workspaceCreated --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::Workspace_Deleted(), function ($payload) {
            log_('Workspace_Deleted');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ workspaceDeleted --identifier $identifier --event $event --data $data");
            log_($result);
        });
    }

    private function setupInternalClient(string $host, LoopInterface $loop): void {
        $m = new AuthMinion();
        $m->register(function (Client $client) {
            log_('Connected to internal host!');
            $this->subscribe($client);

            $client->publish('i.am', [
                ['name' => gethostname()],
            ]);

            $this->internalClient = $client;
        });

        log_("Connecting to $host port 9100");
        $m->run(
            [
                'realm' => 'realm1',
                'host' => $host,
                'port' => 9100,
                'auth' => [
                    'authid' => 'wampcra',
                    'secret' => '3,fqm34f34kf340f3k4qf9'
                ]
            ], $loop
        );
    }

    private static function ParsePayload($payload): array {
        $raw = json_decode($payload[0], true);
        return [
            $raw['identifier'],
            $raw['event'],
            base64_encode(json_encode($raw['data']))
        ];
    }

    private function heartbeat(): void {
        log_('heartbeat');
        if (isset($this->internalClient)) {
            if ($this->internalClient->getSession() !== null) {
                $this->internalClient->publish('heartbeat');
            } else {
                log_('heartbeat to internal client failed - connection lost');
            }
        }
        if (isset($this->externalClient)) {
            if ($this->externalClient->getSession() !== null) {
                $this->externalClient->publish('heartbeat');
            } else {
                log_('heartbeat to external client failed - connection lost');
            }
        }
    }

}
