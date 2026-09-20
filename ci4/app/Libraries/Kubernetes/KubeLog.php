<?php namespace App\Libraries\Kubernetes;

use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Exceptions\KubernetesLogsException;
use RenokiCo\PhpK8s\Exceptions\KubernetesWatchException;
use RenokiCo\PhpK8s\KubernetesCluster;

class KubeLog {

    private KubernetesCluster $cluster;

    public function __construct(KubernetesCluster $cluster) {
        $this->cluster = $cluster;
    }

    /**
     * @throws KubernetesAPIException
     * @throws KubernetesLogsException
     */
    public function getLogs(string $namespace, string $podName, string $containerName): array {
        $pod = $this->cluster->getPodByName($podName, $namespace);
        return KubeHelper::LogLines($pod->containerLogs($containerName, [
            'tailLines' => 100,
            'timestamps' => true,
        ]));
    }

    /**
     * @throws KubernetesWatchException
     * @throws KubernetesLogsException
     */
    public function watchLog(string $namespace, string $podName, string $containerName): void {
        $pod = $this->cluster->getPodByName($podName, $namespace);
        $pod->watchContainerLogs($containerName, function ($logs) use ($containerName, $podName) {
            // The same split as `getLogs()`, which it used to differ from by one character.
            $lines = KubeHelper::LogLines($logs);

            ZMQProxy::getInstance()->send(
                Events::KubernetesPod_Logs_Watch($podName, $containerName),
                (new ChangeEvent(null, $lines))->toArray()
            );
        }, ['tailLines' => 1, 'timestamps' => true]);
    }

}
