<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\DatabaseService;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationDeploymentAnnotation;
use App\Entities\DeploymentSpecificationInitContainer;
use App\Entities\DeploymentSpecificationVolume;
use App\Entities\DeploymentVolume;
use App\Entities\EnvironmentVariable;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\ContainerImageModel;
use App\Models\DeploymentSpecificationDeploymentAnnotationModel;
use App\Models\DeploymentSpecificationInitContainerModel;
use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use App\Models\EnvironmentVariableModel;
use App\Models\InitContainerModel;
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

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Deployment';
    }

    public function getTriggers(): array {
        return [
            DeploymentStepTriggers::Deployment_Volume_Updated,
            DeploymentStepTriggers::Deployment_Environment_Updated,
            DeploymentStepTriggers::Deployment_EnvironmentVariable_Updated,
            DeploymentStepTriggers::Deployment_ResourceManagement_Updated,
            DeploymentStepTriggers::Deployment_UpdateManagement_Updated,
            DeploymentStepTriggers::Deployment_Version_Updated,
        ];
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

    public function getSuccessStatus(Deployment $deployment): string {
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
        if ($deployment->replicas < 1) {
            return 'Replicas must be > 0';
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

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);

        if ($reason) {
            $annotations = $resource->getAnnotations();
            $annotations['kubernetes.io/change-cause'] = $reason;
            $resource->setAnnotations($annotations);
        }

        $template = $resource->getTemplate();
        $annotations = $template->getAnnotations();
        $annotations['4spaces.kso/update-time'] = date('Y-m-d H:i:s');
        $template->setAnnotations($annotations);
        $resource->setTemplate($template);

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
    public function executeCommand(Deployment $deployment, string $container, array $command, bool $forAll): array {
        Data::debug('executeCommand for', $deployment->name, $deployment->namespace);
        $pods = $this->getPods($deployment);
        Data::debug('found', count($pods), 'running pods');
        $log = [];
        foreach ($pods as $pod) {
            if ($pod->isRunning()) {
                $messages = $pod->exec($command, $container);
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
        $pods = $cluster->getAllPods(
            $deployment->namespace,
            [
                'labelSelector' => urldecode(http_build_query(
                    [
                        "app" => "$deployment->name,role=app",
                    ]
                ))
            ]
        );
        $items = [];
        /** @var K8sPod $pod */
        foreach ($pods as $pod) {
            Data::debug("Pod found for", $deployment->name, 'with name', $pod->getName(), $pod->isRunning() ? 'is running' : 'is not running');
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

        // Security Context
        if (strlen($spec->container_image->security_context_run_as_user) > 0) {
            $container->setAttribute('securityContext.runAsUser', (int)$spec->container_image->security_context_run_as_user);
        }
        if (strlen($spec->container_image->security_context_run_as_group) > 0) {
            $container->setAttribute('securityContext.runAsGroup', (int)$spec->container_image->security_context_run_as_group);
        }
        $container->setAttribute('securityContext.allowPrivilegeEscalation', (bool)$spec->container_image->security_context_allow_privilege_escalation);
        $container->setAttribute('securityContext.readOnlyRootFilesystem', (bool)$spec->container_image->security_context_read_only_root_filesystem);

        // Init Containers
        $initContainers = [];
        /** @var DeploymentSpecificationInitContainer $deploymentSpecificationInitContainers */
        $deploymentSpecificationInitContainers = (new DeploymentSpecificationInitContainerModel())
            ->includeRelated([InitContainerModel::class, ContainerImageModel::class])
            ->where('deployment_specification_id', $spec->id)
            ->orderBy('position', 'asc')
            ->find();
        foreach ($deploymentSpecificationInitContainers as $deploymentSpecificationInitContainer) {
            $initContainers[] = $deploymentSpecificationInitContainer->init_container->toKubernetesResource($deployment);
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

            $volumes[] = $volume;
        }
        /** @var DeploymentSpecificationVolume $deploymentSpecificationVolumes */
        $deploymentSpecificationVolumes = (new DeploymentSpecificationVolumeModel())
            ->where('deployment_specification_id', $spec->id)
            ->find();
        foreach ($deploymentSpecificationVolumes as $deploymentSpecificationVolume) {
            $volume = new Volume();
            $volume
                ->setAttribute('name', $deployment->name)
                ->setAttribute('persistentVolumeClaim', [
                    'claimName' => $deployment->name,
                ]);

            $container->addMountedVolume($volume->mountTo(
                $deploymentSpecificationVolume->mount_path,
                $deploymentSpecificationVolume->getCompiledSubPath($deployment)
            ));

            $volumes[] = $volume;
        }

        $template = new K8sPod();
        $template
            ->setAttribute('metadata', [
                'labels' => [
                    'app' => $deployment->name,
                    'role' => 'app',
                ],
            ])
            ->setContainers([
                $container
            ]);

        if (strlen($spec->container_image->pull_secret) > 0) {
            $template->setSpec('imagePullSecrets', [
                [
                    'name' => $spec->container_image->pull_secret,
                ],
            ]);
        }

        if (count($initContainers) > 0) {
            $template->setInitContainers($initContainers);
        }
        if (count($volumes) > 0) {
            $template->setVolumes($volumes);
        }

        if ($spec->hasDeploymentStep($deployment, ServiceAccountStep::class)) {
            $template->setSpec('serviceAccountName', $deployment->name);
        }

        $annotations = [
            'app.kubernetes.io/managed-by' => 'kso',
        ];

        /** @var DeploymentSpecificationDeploymentAnnotation $deploymentAnnotation */
        $deploymentAnnotations = (new DeploymentSpecificationDeploymentAnnotationModel())
            ->where('deployment_specification_id', $spec->id)
            ->find();
        foreach ($deploymentAnnotations as $deploymentAnnotation) {
            $annotations[$deploymentAnnotation->name] = $deploymentAnnotation->value;
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
