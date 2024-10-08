<?php namespace App;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Vinelab\Minion\Client;

class Subscriber {

    private Client $internalClient;

    public function __construct() {
        $internalHost = 'localhost';

        $loop = Loop::get();

        $this->setupInternalClient($internalHost, $loop);

        $loop->addPeriodicTimer(300, fn() => $this->heartbeat());

        $loop->run();
    }

    private function subscribe(Client $client): void {
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

        $client->subscribe(Events::Workspace_Updated(), function ($payload) {
            log_('Workspace_Updated');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ workspaceUpdated --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::Workspace_Deleted(), function ($payload) {
            log_('Workspace_Deleted');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ workspaceDeleted --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::Workspace_Deployed(), function ($payload) {
            log_('Workspace_Deployed');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ workspaceDeployed --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::Workspace_Terminated(), function ($payload) {
            log_('Workspace_Terminated');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ workspaceTerminated --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::Deployment_Deployed(), function ($payload) {
            log_('Deployment_Deployed');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ deploymentDeployed --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::Deployment_Terminated(), function ($payload) {
            log_('Deployment_Terminated');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ deploymentTerminated --identifier $identifier --event $event --data $data");
            log_($result);
        });

        $client->subscribe(Events::AutoUpdate_Approved(), function ($payload) {
            log_('AutoUpdate_Approved');
            [$identifier, $event, $data] = self::ParsePayload($payload);
            $result = php("ZMQ autoUpdateApproved --identifier $identifier --event $event --data $data");
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
