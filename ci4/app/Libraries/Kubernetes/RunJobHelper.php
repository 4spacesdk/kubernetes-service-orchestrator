<?php namespace App\Libraries\Kubernetes;

use App\Entities\ContainerImage;
use App\Entities\Deployment;
use App\Entities\DeploymentVolume;
use App\Entities\EnvironmentVariable;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\Models\DeploymentVolumeModel;
use App\Models\EnvironmentVariableModel;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Exceptions\KubernetesLogsException;
use RenokiCo\PhpK8s\Instances\Container;
use RenokiCo\PhpK8s\Instances\Volume;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\Kinds\K8sPod;

class RunJobHelper {

    public function __construct() {
    }

    public function runJob(Deployment $deployment, string $command): ?string {
        $jobId = uniqid();

        try {
            $this->applyJob($deployment, $command, $jobId);
        } catch (\Exception $e) {
            Data::debug(KubeHelper::PrintException($e));
            Data::debug("failed to apply job");
            return null;
        }
        Data::debug("Created job {$this->getJobName($deployment, $jobId)}, {$deployment->namespace}, {$jobId}");

        try {
            $this->waitForCompletion($deployment, $jobId);
        } catch (\Exception $e) {
            Data::debug(KubeHelper::PrintException($e));
            Data::debug("failed to wait for completion");
            return null;
        }

        try {
            $logs = $this->getLogs($deployment, $jobId);
        } catch (\Exception $e) {
            Data::debug(KubeHelper::PrintException($e));
            Data::debug("failed to get logs");
            return null;
        }

        try {
            $this->deleteJob($deployment, $jobId);
        } catch (\Exception $e) {
            Data::debug(KubeHelper::PrintException($e));
            Data::debug("failed to delete job");
            return null;
        }

        return $logs;
    }

    private function getJobName(Deployment $deployment, string $jobId): string {
        return "{$deployment->name}-run-job-{$jobId}";
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    private function applyJob(Deployment $deployment, string $command, string $jobId): void {
        $spec = $deployment->findDeploymentSpecification();
        $containerImage = new ContainerImage();
        $containerImage->find($spec->container_image_id);

        $container = new Container();
        $container
            ->setAttribute('name', $deployment->name)
            ->setImage($containerImage->url, $deployment->version)
            ->setAttribute('imagePullPolicy', 'Always')
            ->setAttribute('command', [
                '/bin/sh'
            ])
            ->setAttribute('args', [
                '-c',
                $command
            ])
            ->addEnv('ENVIRONMENT', $deployment->environment)
            ->addEnv('BASE_URL', $deployment->getUrl(true, true));

        $extraEnvVars = [];
        $specEnvVars = $spec->getEnvironmentVariables($deployment);
        foreach ($specEnvVars as $key => $value) {
            $extraEnvVars[$key] = $value;
        }
        /** @var EnvironmentVariable $envVars */
        $envVars = (new EnvironmentVariableModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        foreach ($envVars as $envVar) {
            $extraEnvVars[$envVar->name] = $envVar->value;
        }
        $container->addEnvs($extraEnvVars);

        $volumes = [];
        /** @var DeploymentVolume $deploymentVolumes */
        $deploymentVolumes = (new DeploymentVolumeModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        foreach ($deploymentVolumes as $deploymentVolume) {
            $volume = new Volume();
            $volume
                ->setAttribute('name', $deployment->name)
                ->setAttribute('persistentVolumeClaim', [
                    'claimName' => $deployment->name,
                ]);

            $container->addMountedVolume($volume->mountTo($deploymentVolume->mount_path, $deploymentVolume->sub_path));

            $volumes[] = $volume;
        }

        $template = new K8sPod();
        $template
            ->setAttribute('metadata', [
                'labels' => [
                    'app' => 'run-job',
                    'job-id' => $jobId,
                ],
            ])
            ->setContainers([
                $container
            ])
            ->neverRestart();

        if (strlen($containerImage->pull_secret) > 0) {
            $template->setSpec('imagePullSecrets', [
                [
                    'name' => $containerImage->pull_secret,
                ],
            ]);
        }

        if (count($volumes) > 0) {
            $template->setVolumes($volumes);
        }

        if ($spec->hasDeploymentStep($deployment, ServiceAccountStep::class)) {
            $template->setSpec('serviceAccountName', $deployment->name);
        }

        $resource = new K8sJob();
        $resource
            ->setName($this->getJobName($deployment, $jobId))
            ->setNamespace($deployment->namespace)
            ->setTemplate($template)
            ->setSpec('activeDeadlineSeconds', MINUTE)
            ->setSpec('completions', 1)
            ->setSpec('parallelism', 1)
            ->setSpec('backoffLimit', 1);
        $auth = new KubeAuth();
        $resource->onCluster($auth->authenticate());

        if ($resource->exists()) {
            try {
                /** @var K8sJob $existing */
                $existing = $resource->get();
                $existing->delete(['pretty' => 1], 0);

                // Wait for completion
                $stop = 10;
                do {
                    $existing = $resource->get();
                } while ($existing->exists() && $stop-- > 0 && sleep(2) === 0);
            } catch (\Exception $e) {
                // Keep going.
            }
        }

        $resource->create();
    }

    /**
     * @throws \Exception
     */
    private function waitForCompletion(Deployment $deployment, string $jobId): void {
        $ticks = 0;
        $cluster = (new KubeAuth())->authenticate();
        $job = $cluster->getJobByName($this->getJobName($deployment, $jobId), $deployment->namespace);
        $job->watch(function($data) use ($jobId, $deployment, $cluster, $job, &$ticks) {
//            Data::debug($data);

            $done = false;
            $message = "";
            $failed = false;

            switch ($data) {
                case 'ADDED':
                case 'MODIFIED':
                    $job = $cluster->getJobByName($this->getJobName($deployment, $jobId), $deployment->namespace);
                    $conditions = $job->getStatus('conditions');
//                    Data::debug($conditions);
                    if ($conditions) {
                        foreach ($conditions as $condition) {
                            switch ($condition['type']) {
                                case 'Failed':
                                    $failed = true;
                                    $message = $condition['message'];
                                    $done = true;
                                    break;
                                case 'Complete':
                                    $failed = false;
                                    $message = $condition['message'];
                                    $done = true;
                                    break;
                            }
                        }
                    }
                    break;

                case 'DELETED':
                case 'ERROR':
                    return true; // Stop, failed
            }

            if ($done) {
                if ($failed) {
                    Data::debug("Job failed with message", $message);
                } else {
                    Data::debug("Job succeeded with message", $message);
                }
                return true; // Stop, completed
            }

            if (++$ticks == 10) {
                return true; // Stop, max events
            }
            return null;
        });
    }

    /**
     * @throws KubernetesAPIException
     * @throws KubernetesLogsException
     * @throws \Exception
     */
    private function getLogs(Deployment $deployment, string $jobId): string {
        $cluster = (new KubeAuth())->authenticate();
        $pods = $cluster->getAllPods(
            $deployment->namespace,
            [
                'labelSelector' => urldecode(http_build_query(
                    [
                        "app" => "run-job,job-id=$jobId",
                    ]
                ))
            ]
        );
        Data::debug("found", count($pods), 'in namespace', $deployment->namespace, 'labelSelector', "run-job,job-id=$jobId");
        $log = [];
        /** @var K8sPod $pod */
        foreach ($pods as $pod) {
            $log[] = $pod->logs([]);
        }

        return implode("\n", $log);
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    private function deleteJob(Deployment $deployment, string $jobId): void {
        $cluster = (new KubeAuth())->authenticate();
        $job = $cluster->getJobByName($this->getJobName($deployment, $jobId), $deployment->namespace);
        /** @var K8sPod $pod */
        foreach ($job->getPods() as $pod) {
            $pod->delete();
        }
        $job->delete(
            ['pretty' => 1],
            null,
            'DeletePropagationBackground'
        );
    }

}
