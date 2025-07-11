<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\ContainerImage;
use App\Entities\DatabaseService;
use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationInitContainer;
use App\Entities\DeploymentSpecificationVolume;
use App\Entities\DeploymentVolume;
use App\Entities\EnvironmentVariable;
use App\Entities\MigrationJob;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use App\Models\ContainerImageModel;
use App\Models\DeploymentSpecificationInitContainerModel;
use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use App\Models\EnvironmentVariableModel;
use App\Models\InitContainerModel;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Container;
use RenokiCo\PhpK8s\Instances\Volume;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\Kinds\K8sPod;

class MigrationJobStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Migration;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Migration Job';
    }

    public function getTriggers(): array {
        return [
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
        return false;
    }

    public function hasKubernetesStatus(): bool {
        return true;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::MigrationJob_Completed;
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
            /** @var K8sJob $resource */
            $resource = $resource->get();
            $completionTime = $resource->getStatus('completionTime');
            $startTime = $resource->getStatus('startTime');
            if ($completionTime) {
                return DeploymentStepHelper::MigrationJob_Completed;
            }
            return DeploymentStepHelper::MigrationJob_Running;
        } else {
            return DeploymentStepHelper::MigrationJob_NotFound;
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

        if (!$deployment->database_service_id) {
            return 'Missing database service';
        }
        $databaseService = new DatabaseService();
        $databaseService->find($deployment->database_service_id);
        if (!$databaseService->exists()) {
            return 'Database service no longer exists';
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

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resource = $this->getResource($deployment, true);
        if ($resource->exists()) {
            try {
                Data::debug('migration resource exists, deleting now.');
                /** @var K8sJob $existing */
                $existing = $resource->get();
                $existing->delete(['pretty' => 1], 0);

                // Wait for completion
                $stop = 10;
                do {
                    $existing = $resource->get();
                    Data::debug('resource still exists?', $existing->exists());
                } while($existing->exists() && $stop-- > 0 && sleep(2) === 0);
            } catch (\Exception $e) {
                // Keep going.
            }
        }

        $migrationJob = new MigrationJob();
        $migrationJob->deployment_id = $deployment->id;
        $migrationJob->save();
        $migrationJob->updateStatus(\MigrationJobStatusTypes::Deploying);

        // Extract container, add environment variable, reattach container and template
        $template = $resource->getTemplate();
        /** @var Container $container */
        $container = $template->getContainers()[0];
        $container->addEnv('MIGRATION_JOB_ID', (string)$migrationJob->id);
        $template->setContainers([$container]);
        $resource->setTemplate($template);

        $migrationJob->image = $container->getAttribute('image');
        $migrationJob->command = $deployment->findDeploymentSpecification()->database_migration_command;
        $migrationJob->save();

        $resource->create();
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
        /** @var K8sJob $resource */
        $resource = $this->getResource($deployment, true)->get();
        $status = $resource->getAttribute('status');
        return $status;
    }

    /**
     * @throws \Exception
     */
    private function getResource(Deployment $deployment, bool $auth = false): K8sJob {
        $spec = $deployment->findDeploymentSpecification();

        $containerImage = new ContainerImage();

        if ($spec->database_migration_container_image_id) {
            $containerImage->find($spec->database_migration_container_image_id);
            $tag = match ($spec->database_migration_container_image_tag_policy) {
                \ContainerImageTagPolicies::MatchDeployment => $deployment->version,
                \ContainerImageTagPolicies::Static => $spec->database_migration_container_image_tag_value
            };
        } else {
            $containerImage->find($spec->container_image_id);
            $tag = $deployment->version;
        }

        $container = new Container();
        $container
            ->setAttribute('name', $deployment->name)
            ->setImage($containerImage->url, $tag)
            ->setAttribute('imagePullPolicy', 'Always')
            ->setAttribute('command', [
                '/bin/sh'
            ])
            ->setAttribute('args', [
                '-c',

                // Tell KSO about migration job started
                'curl -i -v -X PUT ' . $this->getMigrationStartedUrl()

                // Perform migration
                . ' && ' . $spec->database_migration_command

                // Tell KSO about migration job ended
                . ' | curl --connect-timeout 5 --max-time 300 --retry 10 --retry-delay 5 --retry-max-time 300 -i -v -X PUT --data-binary @- ' . $this->getMigrationEndedUrl(),
            ])
            ->addEnv('ENVIRONMENT', \Environments::Development)
            ->addEnv('BASE_URL', $deployment->getUrl(true, true));

        // Security Context
        if (strlen($containerImage->security_context_run_as_user) > 0) {
            $container->setAttribute('securityContext.runAsUser', (int)$containerImage->security_context_run_as_user);
        }
        if (strlen($containerImage->security_context_run_as_group) > 0) {
            $container->setAttribute('securityContext.runAsGroup', (int)$containerImage->security_context_run_as_group);
        }
        $container->setAttribute('securityContext.allowPrivilegeEscalation', (bool)$containerImage->security_context_allow_privilege_escalation);
        $container->setAttribute('securityContext.readOnlyRootFilesystem', (bool)$containerImage->security_context_read_only_root_filesystem);

        // Init Containers
        $initContainers = [];
        /** @var DeploymentSpecificationInitContainer $deploymentSpecificationInitContainers */
        $deploymentSpecificationInitContainers = (new DeploymentSpecificationInitContainerModel())
            ->includeRelated([InitContainerModel::class, ContainerImageModel::class])
            ->where('deployment_specification_id', $spec->id)
            ->where('include_in_migration_job', true)
            ->orderBy('position', 'asc')
            ->find();
        foreach ($deploymentSpecificationInitContainers as $deploymentSpecificationInitContainer) {
            $initContainers[] = $deploymentSpecificationInitContainer->init_container->toKubernetesResource($deployment);
        }

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
                    'role' => 'migration',
                ],
            ])
            ->setContainers([
                $container
            ])
            ->neverRestart();

        if (strlen($containerImage->security_context_fs_group) > 0) {
            $template->setSpec('securityContext.fsGroup', (int)$containerImage->security_context_fs_group);
        }

        if (strlen($containerImage->pull_secret) > 0) {
            $template->setSpec('imagePullSecrets', [
                [
                    'name' => $containerImage->pull_secret,
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

        $resource = new K8sJob();
        $resource
            ->setName($deployment->name)
            ->setNamespace($deployment->namespace)
            ->setTemplate($template)
            ->setSpec('activeDeadlineSeconds', 21600);

        if ($auth) {
            $auth = new KubeAuth();
            $resource->onCluster($auth->authenticate());
        }

        return $resource;
    }

    private function getMigrationStartedUrl(): string {
        if (getenv('DEV_REMOTE_BASE_URL')) {
            $domain = getenv('DEV_REMOTE_BASE_URL');
        } else {
            $domain = KubeHelper::GetMyHostname() . '.' . KubeHelper::GetMyNamespace();
        }
        return "$domain/api/migration-jobs/$(MIGRATION_JOB_ID)/started";
    }

    private function getMigrationEndedUrl(): string {
        if (getenv('DEV_REMOTE_BASE_URL')) {
            $domain = getenv('DEV_REMOTE_BASE_URL');
        } else {
            $domain = KubeHelper::GetMyHostname() . '.' . KubeHelper::GetMyNamespace();
        }
        return "$domain/api/migration-jobs/$(MIGRATION_JOB_ID)/ended";
    }

}
