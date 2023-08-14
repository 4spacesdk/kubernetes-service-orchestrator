<?php namespace App;

use React\EventLoop\LoopInterface;
use Thruway\Authentication\ClientWampCraAuthenticator;
use Vinelab\Minion\Minion;

class AuthMinion extends Minion {

    public function run($options = [], LoopInterface $loop = null) {
        $this->mergeConfig($options);

        // Print out our lovely minion.
        echo $this->gimmeASCII() . "\n";

        $client = $this->newClient($loop);
        $client->setReconnectOptions(
            [
                'max_retries' => 10000,
            ]
        );
        $client->addTransportProvider($this->newTransportProvider());

        $auth = $this->getConfig('auth');
        if (!empty($auth)) {
            $client->addClientAuthenticator(
                $this->newAuthenticator($auth['authid'], $auth['secret'])
            );
        }

        return $client->start($this->getConfig('debug'), false);
    }

    public function newAuthenticator($authid, $secret) {
        return new ClientWampCraAuthenticator($authid, $secret);
    }

}
