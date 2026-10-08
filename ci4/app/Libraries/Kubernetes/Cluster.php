<?php namespace App\Libraries\Kubernetes;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * php-k8s' cluster, with a limit on how long reaching the api server may take - the name looked
 * up and the connection made. Without one curl waits 300 seconds, and a DNS lookup that hung held
 * an auto-update for five minutes before it failed. Once connected, a request takes as long as it
 * takes.
 *
 * Built by `KubeAuth`, as are `IndexedCluster` and `StreamingCluster`.
 */
class Cluster extends KubernetesCluster {

    public const int ConnectTimeoutSeconds = 10;

    public function getClient() {
        return new Client([
            ...parent::getClient()->getConfig(),
            RequestOptions::CONNECT_TIMEOUT => self::ConnectTimeoutSeconds,
        ]);
    }

}
