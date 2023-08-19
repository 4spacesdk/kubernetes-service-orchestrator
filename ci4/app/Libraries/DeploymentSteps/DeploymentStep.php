<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\DatabaseService;
use App\Entities\Deployment;
use App\Entities\DeploymentVolume;
use App\Entities\Domain;
use App\Entities\EnvironmentVariable;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\DeploymentVolumeModel;
use App\Models\EnvironmentVariableModel;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Container;
use RenokiCo\PhpK8s\Instances\Volume;
use RenokiCo\PhpK8s\Kinds\K8sDeployment;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sPod;

class DeploymentStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Deployment;
    }

    public function getName(): string {
        return 'Deployment';
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
        return DeploymentStepHelper::Deployment_Found;
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
            unset($remote['metadata']['annotations']['deployment.kubernetes.io/revision']);
            unset($remote['metadata']['annotations']['kubectl.kubernetes.io/last-applied-configuration']);
            unset($remote['metadata']['annotations']['kubernetes.io/change-cause']);
            unset($remote['metadata']['managedFields']);
            unset($remote['spec']['template']['metadata']['creationTimestamp']);
            unset($remote['spec']['template']['metadata']['annotations']);
            unset($remote['spec']['template']['spec']['containers'][0]['terminationMessagePath']);
            unset($remote['spec']['template']['spec']['containers'][0]['terminationMessagePolicy']);
            unset($remote['spec']['template']['spec']['restartPolicy']);
            unset($remote['spec']['template']['spec']['terminationGracePeriodSeconds']);
            unset($remote['spec']['template']['spec']['dnsPolicy']);
            unset($remote['spec']['template']['spec']['securityContext']);
            unset($remote['spec']['template']['spec']['schedulerName']);
            unset($remote['spec']['strategy']);
            unset($remote['spec']['revisionHistoryLimit']);
            unset($remote['spec']['progressDeadlineSeconds']);
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
            return DeploymentStepHelper::Deployment_Found;
        } else {
            return DeploymentStepHelper::Deployment_NotFound;
        }
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }
        if (strlen($deployment->image) == 0) {
            return 'Missing image';
        }
        if (strlen($deployment->version) == 0) {
            return 'Missing version';
        }
        if (!in_array($deployment->keel_policy, \KeelPolicies::All())) {
            return 'Missing keel policy';
        }
        if (!in_array($deployment->environment, \Environments::All())) {
            return 'Missing environment';
        }
        if ($deployment->replicas < 1) {
            return 'Replicas must be > 0';
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

        $spec = $deployment->findDeploymentSpecification();
        if ($spec->hasDeploymentStep($deployment, DatabaseStep::class)) {
            if (!$deployment->database_service_id) {
                return 'Missing database service';
            }
            $databaseService = new DatabaseService();
            $databaseService->find($deployment->database_service_id);
            if (!$databaseService->exists()) {
                return 'Database service no longer exists';
            }
            $databaseStep = new DatabaseStep();
            if ($databaseStep->getStatus($deployment) != DeploymentStepHelper::DatabaseStatus_Success) {
                return 'Missing Database';
            }
        }
        if ($spec->hasDeploymentStep($deployment, ServiceAccountStep::class)) {
            $serviceAccount = new ServiceAccountStep();
            if ($serviceAccount->getStatus($deployment) != DeploymentStepHelper::ServiceAccount_Found) {
                return 'Missing Service Account';
            }
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
        /** @var K8sDeployment $resource */
        $resource = $this->getResource($deployment, true)->get();
        $status = $resource->getAttribute('status');
        Data::debug($status);
        return $status;
    }

    /**
     * @throws \Exception
     */
    public function hasNonReadyContainer(Deployment $deployment): bool {
        $cluster = (new KubeAuth())->authenticate();
        $resource = $cluster->getDeploymentByName($deployment->name, $deployment->namespace);
        $resource::selectPods(function (K8sDeployment $dep) use($deployment) {
            return [
                "app" => "$deployment->name,role=app",
            ];
        });
        $pods = $resource->getPods();
        $hasNonReadyContainer = false;
        /** @var K8sPod $pod */
        foreach ($pods as $pod) {
            Data::debug("Pod found for", $deployment->name, 'with name', $pod->getName());
            if (!$pod->containersAreReady()) {
                $hasNonReadyContainer = true;
            }
        }
        return $hasNonReadyContainer;
    }

    /**
     * @throws \Exception
     */
    public function executeCommand(Deployment $deployment, array $command, bool $forAll): array {
        Data::debug('executeCommand for', $deployment->name, $deployment->namespace);
        $pods = $this->getPods($deployment);
        Data::debug('found', count($pods), 'running pods');
        $log = [];
        foreach ($pods as $pod) {
            if ($pod->isRunning()) {
                $messages = $pod->exec($command);
                $all = collect($messages)->where('channel', 'stdout')->all();
                $lines = [];
                foreach ($all as ['channel' => $channel, 'output' => $output]) {
                    $lines[] = $output;
                }
                $lines = explode("\n", implode("\n", $lines)); // K8s returning multiple vars in single line. This will fix that.
                $log = array_merge($log, $lines);

                if (!$forAll) {
                    break;
                }
            }
        }
        return $log;
    }

    /**
     * @return K8sPod[]
     * @throws \Exception
     */
    public function getPods(Deployment $deployment): array {
        $cluster = (new KubeAuth())->authenticate();
        $resource = $cluster->getDeploymentByName($deployment->name, $deployment->namespace);
        $resource::selectPods(function (K8sDeployment $dep) use($deployment) {
            return [
                "app" => "$deployment->name,role=app",
            ];
        });
        $pods = $resource->getPods();
        $items = [];
        /** @var K8sPod $pod */
        foreach ($pods as $pod) {
            Data::debug("Pod found for", $deployment->name, 'with name', $pod->getName());
            $items[] = $pod;
        }
        return $items;
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sDeployment {
        $spec = $deployment->findDeploymentSpecification();

        if (!$spec->container_image->exists()) {
            $spec->container_image->find();
        }
        $container = new Container();
        $container
            ->setAttribute('name', $deployment->name)
            ->setImage($spec->container_image->url, $deployment->version)
            ->setAttribute('imagePullPolicy', 'Always')
            ->addPort(80)
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

        if ($deployment->cpu_request) {
            $container->minCpu($deployment->cpu_request.'m');
        }
        if ($deployment->cpu_limit) {
            $container->maxCpu($deployment->cpu_limit.'m');
        }
        if ($deployment->memory_request) {
            $container->minMemory($deployment->memory_request, 'Mi');
        }
        if ($deployment->memory_limit) {
            $container->maxMemory($deployment->memory_limit, 'Mi');
        }

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
        }

        $template = new K8sPod();
        $template
            ->setAttribute('metadata', [
                'labels' => [
                    'app' => $deployment->name,
                    'role' => 'app',
                ],
            ])
            ->setSpec('imagePullSecrets', [
                [
                    'name' => 'gcr-service-account',
                ],
            ])
            ->setContainers([
                $container
            ])
            ->setVolumes($volumes);

        if ($spec->hasDeploymentStep($deployment, ServiceAccountStep::class)) {
            $template->setSpec('serviceAccountName', $deployment->name);
        }

        $annotations = [
            'keel.sh/policy' => $deployment->keel_policy,
        ];

        switch (getenv('KEEL_TRIGGER')) {
            case 'poll':
                $annotations['keel.sh/trigger'] = 'poll';
                $annotations['keel.sh/pollSchedule'] = '@every 2m';
                break;
            default:
            case 'push':
                // Use keel defaults
                break;
        }

        if (!$deployment->keel_auto_update) {
            $annotations['keel.sh/approvals'] = '1';
        }

        $resource = new K8sDeployment();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setAnnotations($annotations)
            ->setSelectors([
                'matchLabels' => [
                    'app' => $deployment->name,
                    'role' => 'app',
                ],
            ])
            ->setSpec('replicas', $deployment->replicas)
            ->setTemplate($template)
            ->setReplicas($deployment->replicas ?? 1);


        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
