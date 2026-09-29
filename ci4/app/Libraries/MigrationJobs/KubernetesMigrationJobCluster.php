<?php namespace App\Libraries\MigrationJobs;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * `MigrationJobCluster` against the real cluster. Needs `get` on jobs, `list` on pods, `get` on
 * `pods/log` and `delete` on jobs - all in the chart's ClusterRole for the deploy steps already.
 */
class KubernetesMigrationJobCluster implements MigrationJobCluster {

    private ?KubernetesCluster $cluster = null;

    private function cluster(): KubernetesCluster {
        return $this->cluster ??= (new KubeAuth())->authenticate();
    }

    public function job(Deployment $deployment): ?array {
        try {
            $job = $this->cluster()->getJobByName((string) $deployment->name, (string) $deployment->namespace);
        } catch (KubernetesAPIException $e) {
            if ($e->getCode() === KubeHelper::NotFoundCode) {
                return null;
            }
            throw $e;
        }

        return json_decode($job->toJson(), true);
    }

    public function pods(Deployment $deployment): array {
        $pods = [];
        /** @var K8sPod $pod */
        foreach ($this->cluster()->getAllPods((string) $deployment->namespace, ['labelSelector' => "app={$deployment->name},role=migration"]) as $pod) {
            $pods[] = json_decode($pod->toJson(), true);
        }

        return $pods;
    }

    public function log(Deployment $deployment, string $pod, string $container): string {
        return (string) $this->cluster()->getPodByName($pod, (string) $deployment->namespace)->containerLogs($container, []);
    }

    public function deleteJob(Deployment $deployment): void {
        $job = $this->cluster()->getJobByName((string) $deployment->name, (string) $deployment->namespace);
        // `Background`, so the pod goes with it - see `RunJobHelper::deleteJob()`.
        $job->delete(['pretty' => 1], null, 'Background');
    }

}
