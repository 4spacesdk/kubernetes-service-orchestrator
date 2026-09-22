<?php namespace App\Libraries\Kubernetes;

use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
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
     * @param bool $previous The container that ran before this one - what a crash left behind, and
     *   gone from the container that replaced it.
     * @param int|null $sinceSeconds Only what was written in the last so many seconds.
     * @throws KubernetesAPIException
     * @throws KubernetesLogsException
     */
    public function getLogs(string $namespace, string $podName, string $containerName, bool $previous = false, ?int $sinceSeconds = null): array {
        $pod = $this->cluster->getPodByName($podName, $namespace);
        return KubeHelper::LogLines($pod->containerLogs($containerName, LogQuery::For($previous, $sinceSeconds)));
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

            Publisher::getInstance()->send(
                Events::KubernetesPod_Logs_Watch($podName, $containerName),
                (new ChangeEvent(null, $lines))->toArray()
            );
        }, ['tailLines' => 1, 'timestamps' => true]);
    }

}
