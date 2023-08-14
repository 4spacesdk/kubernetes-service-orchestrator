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
    public function getLogs(string $namespace, string $podName): array {
        $pod = $this->cluster->getPodByName($podName, $namespace);
        $logs = explode("\n", $pod->logs([
            'tailLines' => 100,
            'timestamps' => true,
        ]));
        $lines = [];
        foreach ($logs as $log) {
            if (strlen($log)) {
                $lines[] = [
                    'date' => substr($log, 0, 30),
                    'line' => substr($log, 31),
                ];
            }
        }
        return $lines;
    }

    /**
     * @throws KubernetesWatchException
     * @throws KubernetesLogsException
     */
    public function watchLog(string $namespace, string $podName): void {
        $pod = $this->cluster->getPodByName($podName, $namespace);
        $pod->watchLogs(function ($logs) use ($podName) {
            $lines = [];
            foreach (explode("\n", $logs) as $log) {
                if (strlen($log)) {
                    $lines[] = [
                        'date' => substr($log, 0, 30),
                        'line' => substr($log, 30),
                    ];
                }
            }

            ZMQProxy::getInstance()->send(
                Events::KubernetesPod_Logs_Watch($podName),
                (new ChangeEvent(null, $lines))->toArray()
            );
        }, ['tailLines' => 1, 'timestamps' => true]);
    }

}
