<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\Domain;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use Cron\CronExpression;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Container;
use RenokiCo\PhpK8s\Kinds\K8sCronJob;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\Kinds\K8sPod;

class CronjobStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Cronjob;
    }

    public function getName(): string {
        return 'Cron Job';
    }

    public function hasPreviewCommand(): bool {
        return true;
    }

    public function hasStatusCommand(): bool {
        return true;
    }

    public function hasDeployCommand(): bool {
        return true;
    }

    public function hasTerminateCommand(): bool {
        return true;
    }

    public function hasKubernetesEvents(): bool {
        return true;
    }

    public function hasKubernetesStatus(): bool {
        return true;
    }

    public function getSuccessStatus(): string {
        return DeploymentStepHelper::Cronjob_Found;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        $local = $resource->toJson();

        if ($resource->exists()) {
            $exiting = $resource->get();
            $remote = json_decode($exiting->toJson(), true);
            unset($remote['metadata']['uid']);
            unset($remote['metadata']['resourceVersion']);
            unset($remote['metadata']['generation']);
            unset($remote['metadata']['creationTimestamp']);
            unset($remote['metadata']['annotations']);
            unset($remote['metadata']['managedFields']);
            unset($remote['spec']['concurrencyPolicy']);
            unset($remote['spec']['suspend']);
            unset($remote['spec']['jobTemplate']['metadata']);
            unset($remote['spec']['jobTemplate']['spec']['template']['metadata']);
            unset($remote['spec']['jobTemplate']['spec']['template']['spec']['containers'][0]['resources']);
            unset($remote['spec']['jobTemplate']['spec']['template']['spec']['containers'][0]['terminationMessagePath']);
            unset($remote['spec']['jobTemplate']['spec']['template']['spec']['containers'][0]['terminationMessagePolicy']);
            unset($remote['spec']['jobTemplate']['spec']['template']['spec']['terminationGracePeriodSeconds']);
            unset($remote['spec']['jobTemplate']['spec']['template']['spec']['dnsPolicy']);
            unset($remote['spec']['jobTemplate']['spec']['template']['spec']['securityContext']);
            unset($remote['spec']['jobTemplate']['spec']['template']['spec']['schedulerName']);
            unset($remote['spec']['successfulJobsHistoryLimit']);
            unset($remote['spec']['failedJobsHistoryLimit']);
            unset($remote['status']);
            $remote = json_encode($remote);
        }

        return json_encode([
            'local' => $local,
            'remote' => $remote ?? null,
        ]);
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getStatus(Deployment $deployment): string {
        $resource = $this->getResource($deployment, true);
        if ($resource->exists()) {
            return DeploymentStepHelper::Cronjob_Found;
        } else {
            return DeploymentStepHelper::Cronjob_NotFound;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        if (!$deployment->domain_id) {
            return 'Missing domain';
        }
        $domain = new Domain();
        $domain->find($deployment->domain_id);
        if (!$domain->exists()) {
            return 'domain no longer exists';
        }

        $namespaceStep = new NamespaceStep();
        if ($namespaceStep->getStatus($deployment) != DeploymentStepHelper::Namespace_Found) {
            return 'Missing Namespace';
        }

        $ingressStep = new IngressStep();
        if ($ingressStep->getStatus($deployment) != DeploymentStepHelper::Ingress_Found) {
            return 'Missing Ingress';
        }
        return null;
    }

    public function startDeployCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        $resource->createOrUpdate();
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resource = $this->getResource($deployment, true);
        $resource->synced();
        $resource->delete();
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        $resource = $this->getResource($deployment, true);
        $events = [];
        /** @var K8sEvent $event */
        foreach ($resource->getEvents() as $event) {
            $events[] = [
                'count' => $event->getAttribute('count'),
                'type' => $event->getAttribute('type'),
                'reason' => $event->getAttribute('reason'),
                'date' => date('Y-m-d H:i:s', strtotime_($event->getAttribute('lastTimestamp'))),
                'from' => $event->getAttribute('source')['component'],
                'message' => $event->getAttribute('message'),
            ];
        }
        return $events;
    }

    public function getKubernetesStatus(Deployment $deployment): array {
        /** @var K8sCronJob $resource */
        $resource = $this->getResource($deployment, true)->get();
        $status = $resource->getAttribute('status');
        Data::debug($status);
        return $status;
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sCronJob {
        $spec = $deployment->findDeploymentSpecification();

        $resource = new K8sCronJob();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setSchedule(new CronExpression('* * * * *'))
            ->setJobTemplate((new K8sJob())
                ->setTemplate((new K8sPod())
                    ->setContainers([
                        (new Container())
                            ->setAttribute('name', 'callout')
                            ->setImage('buildpack-deps', 'curl')
                            ->setAttribute('args', [
                                '/bin/sh',
                                '-ec',
                                "curl {$deployment->name}.{$deployment->namespace}" . $spec->cronjob_url
                            ])
                            ->setAttribute('imagePullPolicy', 'Always')
                    ])
                    ->neverRestart()
                )
            );

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
