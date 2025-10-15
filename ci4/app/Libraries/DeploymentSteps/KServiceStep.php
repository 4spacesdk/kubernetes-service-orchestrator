<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\DatabaseService;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationDeploymentAnnotation;
use App\Entities\DeploymentSpecificationInitContainer;
use App\Entities\DeploymentSpecificationVolume;
use App\Entities\DeploymentVolume;
use App\Entities\Domain;
use App\Entities\EnvironmentVariable;
use App\Entities\KNativeMinScaleSchedule;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sKNativeService;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\ContainerImageModel;
use App\Models\DeploymentSpecificationDeploymentAnnotationModel;
use App\Models\DeploymentSpecificationInitContainerModel;
use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use App\Models\EnvironmentVariableModel;
use App\Models\InitContainerModel;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Instance;
use RenokiCo\PhpK8s\Kinds\K8sEvent;

class KServiceStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::KService;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'KService';
    }

    public function getTriggers(): array {
        return [
            DeploymentStepTriggers::Deployment_Volume_Updated,
            DeploymentStepTriggers::Deployment_Environment_Updated,
            DeploymentStepTriggers::Deployment_EnvironmentVariable_Updated,
            DeploymentStepTriggers::Deployment_ResourceManagement_Updated,
            DeploymentStepTriggers::Deployment_UpdateManagement_Updated,
            DeploymentStepTriggers::Deployment_Version_Updated,
            DeploymentStepTriggers::Deployment_KNativeMinScale_Updated,
            DeploymentStepTriggers::Deployment_ImagePullPolicy_Updated,
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
        return DeploymentStepHelper::KService_Found;
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
            unset($remote['metadata']['creationTimestamp']);
            unset($remote['metadata']['annotations']['kubectl.kubernetes.io/last-applied-configuration']);
            unset($remote['metadata']['annotations']['serving.knative.dev/lastModifier']);
            unset($remote['metadata']['generation']);
            unset($remote['spec']['template']['metadata']['creationTimestamp']);
            unset($remote['spec']['template']['metadata']['traffic']);
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
            return DeploymentStepHelper::KService_Found;
        } else {
            return DeploymentStepHelper::KService_NotFound;
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
        if (!in_array($deployment->environment, \Environments::All())) {
            return 'Missing environment';
        }

        $namespaceStep = new NamespaceStep();
        if ($namespaceStep->getStatus($deployment) != DeploymentStepHelper::Namespace_Found) {
            return 'Missing Namespace';
        }

        $spec = $deployment->findDeploymentSpecification();
        if ($spec->enable_external_access) {
            if ($deployment->workspace_id && !$deployment->workspace->exists()) {
                $deployment->workspace->find();
            }
            if (!$deployment->workspace->exists()) {
                return 'Missing workspace';
            }
            if (!$deployment->workspace->domain_id) {
                return 'Missing workspace domain';
            }
            $domain = new Domain();
            $domain->find($deployment->workspace->domain_id);
            if (!$domain->exists()) {
                return 'domain no longer exists';
            }
        }

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

        // Modify KService annotations
        $annotations = $resource->getAnnotations();

        if ($resource->exists()) {
            $remote = $resource->get();
            $annotations['serving.knative.dev/creator'] = $remote->getAnnotation('serving.knative.dev/creator');
        }

        if ($reason) {
            $annotations['kubernetes.io/change-cause'] = $reason;
        }

        $resource->setAnnotations($annotations);

        // Modify Template annotations
        $annotations = $resource->getAttribute('spec.template.metadata.annotations');
        $annotations['4spaces.kso/update-time'] = date('Y-m-d H:i:s');
        $resource->setAttribute('spec.template.metadata.annotations', $annotations);

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
        /** @var K8sKNativeService $resource */
        $resource = $this->getResource($deployment, true)->get();
        $status = $resource->getAttribute('status');
        return $status;
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sKNativeService {
        $spec = $deployment->findDeploymentSpecification();

        if (!$spec->container_image->exists()) {
            $spec->container_image->find();
        }

        // Container
        $container = new Instance();
        $container
            ->setAttribute('name', $deployment->name)
            ->setAttribute('image', $spec->container_image->url . ':' . $deployment->version)
            ->setAttribute('imagePullPolicy', $deployment->image_pull_policy);

        $ports = [];
        foreach ($spec->getHttpProxyRoutes() as $route) {
            $ports[] = [
                'containerPort' => (int)$route->port,
            ];
        }
        $container->setAttribute('ports', $ports);

        // Env vars
        $envVars = [
            'ENVIRONMENT' => $deployment->environment,
            'BASE_URL' => $deployment->getUrl(true, true),
        ];
        $specEnvVars = $spec->getEnvironmentVariables($deployment);
        foreach ($specEnvVars as $key => $value) {
            $envVars[$key] = $value;
        }
        /** @var EnvironmentVariable $deploymentEnvVars */
        $deploymentEnvVars = (new EnvironmentVariableModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        foreach ($deploymentEnvVars as $deploymentEnvVar) {
            $envVars[$deploymentEnvVar->name] = $deploymentEnvVar->value;
        }
        foreach ($envVars as $key => $value) {
            $container->addToAttribute('env', ['name' => $key, 'value' => $value]);
        }

        // Resource limits
        if ($deployment->cpu_request) {
            $container->setAttribute('resources.requests.cpu', $deployment->cpu_request . 'm');
        }
        if ($deployment->cpu_limit) {
            $container->setAttribute('resources.limits.cpu', $deployment->cpu_limit . 'm');
        }
        if ($deployment->memory_request) {
            $container->setAttribute('resources.requests.memory', $deployment->memory_request . 'Mi');
        }
        if ($deployment->memory_limit) {
            $container->setAttribute('resources.limits.memory', $deployment->memory_limit . 'Mi');
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

        if ($spec->enable_volumes) {
            // Volume Mounts
            /** @var DeploymentVolume $deploymentVolumes */
            $deploymentVolumes = (new DeploymentVolumeModel())
                ->where('deployment_id', $deployment->id)
                ->find();
            foreach ($deploymentVolumes as $deploymentVolume) {
                $container->addToAttribute('volumeMounts', [
                    'mountPath' => $deploymentVolume->mount_path,
                    'name' => $deployment->name,
                    'readOnly' => false,
                    'subPath' => strlen($deploymentVolume->sub_path) ? $deploymentVolume->sub_path : null,
                ]);
            }
            /** @var DeploymentSpecificationVolume $deploymentSpecificationVolumes */
            $deploymentSpecificationVolumes = (new DeploymentSpecificationVolumeModel())
                ->where('deployment_specification_id', $spec->id)
                ->find();
            foreach ($deploymentSpecificationVolumes as $deploymentSpecificationVolume) {
                $container->addToAttribute('volumeMounts', [
                    'mountPath' => $deploymentSpecificationVolume->mount_path,
                    'name' => $deployment->name,
                    'readOnly' => false,
                    'subPath' => strlen($deploymentSpecificationVolume->getCompiledSubPath($deployment)) ? $deploymentSpecificationVolume->getCompiledSubPath($deployment) : null,
                ]);
            }
        }

        // Annotations
        $podAnnotations = [
            'app.kubernetes.io/managed-by' => 'kso',
            'autoscaling.knative.dev/minScale' => KNativeMinScaleSchedule::GetCurrentValueForDeployment($deployment),
        ];

        // Let the deployment step override the annotations
        /** @var DeploymentSpecificationDeploymentAnnotation $deploymentAnnotation */
        $deploymentAnnotations = (new DeploymentSpecificationDeploymentAnnotationModel())
            ->where('level', \DeploymentAnnotationLevels::Pod)
            ->where('deployment_specification_id', $spec->id)
            ->find();
        foreach ($deploymentAnnotations as $deploymentAnnotation) {
            $podAnnotations[$deploymentAnnotation->name] = $deploymentAnnotation->value;
        }

        // Template
        $template = new Instance();
        $template
            ->setAttribute('metadata', [
                'labels' => [
                    'app' => $deployment->name,
                    'role' => 'app',
                ],
                'annotations' => $podAnnotations,
            ])
            ->setAttribute('spec', [
                'containers' => [
                    $container->toArray(),
                ],
            ]);

        if ($spec->enable_volumes) {
            // Volumes
            foreach ($deploymentVolumes as $deploymentVolume) {
                $template->addToAttribute('spec.volumes', [
//                'configMap' => [],
//                'emptyDir' => [],
                    'name' => $deployment->name,
                    'persistentVolumeClaim' => [
                        'claimName' => $deployment->name,
                    ],
//                'projected' => [],
//                'secret' => [],
                ]);
            }
            foreach ($deploymentSpecificationVolumes as $deploymentSpecificationVolume) {
                $template->addToAttribute('spec.volumes', [
//                'configMap' => [],
//                'emptyDir' => [],
                    'name' => $deployment->name,
                    'persistentVolumeClaim' => [
                        'claimName' => $deployment->name,
                    ],
//                'projected' => [],
//                'secret' => [],
                ]);
            }
        }

        // Container Concurrency
        if ($deployment->knative_concurrency_limit_soft) {
            $attrs = $template->getAttribute('metadata.annotations');
            $attrs['autoscaling.knative.dev/target'] = $deployment->knative_concurrency_limit_soft;
            $template->setAttribute('metadata.annotations', $attrs);
        }
        if ($deployment->knative_concurrency_limit_hard) {
            $template->setAttribute('spec.containerConcurrency', (int)$deployment->knative_concurrency_limit_hard);
        }

        // Image Pull Secrets
        if (strlen($spec->container_image->pull_secret) > 0) {
            $template->setAttribute('spec.imagePullSecrets', [
                [
                    'name' => $spec->container_image->pull_secret,
                ]
            ]);
        }

        // Pod Security Context
        if (strlen($spec->container_image->security_context_fs_group) > 0) {
            $template->setAttribute('spec.securityContext.fsGroup', (int)$spec->container_image->security_context_fs_group);
        }

        // Init Containers
        $initContainers = [];
        /** @var DeploymentSpecificationInitContainer $deploymentSpecificationInitContainers */
        $deploymentSpecificationInitContainers = (new DeploymentSpecificationInitContainerModel())
            ->includeRelated([InitContainerModel::class, ContainerImageModel::class])
            ->where('deployment_specification_id', $spec->id)
            ->orderBy('position', 'asc')
            ->find();
        foreach ($deploymentSpecificationInitContainers as $deploymentSpecificationInitContainer) {
            $initContainers[] = $deploymentSpecificationInitContainer->init_container->toKubernetesResource($deployment)->toArray();
        }
        if (count($initContainers) > 0) {
            $template->setAttribute('spec.initContainers', $initContainers);
        }

        // Service Account
        if ($spec->hasDeploymentStep($deployment, ServiceAccountStep::class)) {
            $template->setAttribute('spec.serviceAccountName', $deployment->name);
        }

        // Annotations
        $annotations = [
            'app.kubernetes.io/managed-by' => '4spaces.kso',
        ];
        /** @var DeploymentSpecificationDeploymentAnnotation $deploymentAnnotation */
        $deploymentAnnotations = (new DeploymentSpecificationDeploymentAnnotationModel())
            ->where('level', \DeploymentAnnotationLevels::Deployment)
            ->where('deployment_specification_id', $spec->id)
            ->find();
        foreach ($deploymentAnnotations as $deploymentAnnotation) {
            $annotations[$deploymentAnnotation->name] = $deploymentAnnotation->value;
        }

        // KNativeService
        $resource = new K8sKNativeService();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setAnnotations($annotations)
            ->setAttribute('spec', ['template' => $template->toArray()]);

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

}
