<?php namespace App\Entities;

use App\Core\Entity;
use App\Exceptions\ValidationException;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepTriggers;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\DeploymentModel;
use App\Models\DomainModel;
use App\Models\EnvironmentVariableModel;
use App\Models\WorkspaceModel;
use DebugTool\Data;

/**
 * Class Deployment
 * @package App\Entities
 * @property int $workspace_id
 * @property Workspace $workspace
 *
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $name
 * @property string $namespace
 * @property string $image
 * @property string $version
 * @property string $image_pull_policy
 * @property string $environment
 * @property string $status
 * @property string $last_updated
 * @property string $custom_resource
 *
 * @property int $database_service_id
 * @property DatabaseService $database_service
 * @property string $database_user
 * @property string $database_name
 * @property string $database_pass
 *
 * # Update management
 * @property bool $auto_update_enabled
 * @property string $auto_update_tag_regex
 * @property bool $auto_update_require_approval
 *
 * # Resource management
 * @property int $cpu_limit
 * @property int $cpu_request
 * @property int $memory_limit
 * @property int $memory_request
 * @property int $replicas
 * @property int $knative_concurrency_limit_soft
 * @property int $knative_concurrency_limit_hard
 *
 * # Migration Job
 * @property int $last_migration_job_id
 * @property MigrationJob $last_migration_job
 *
 * Many
 * @property EnvironmentVariable $environment_variables
 * @property DeploymentVolume $deployment_volumes
 * @property MigrationJob $last_migration_jobs
 * @property Label $labels
 * @property DeploymentCronJob $deployment_cron_jobs
 *
 * OTF
 * @property string $url_external
 * @property string $url_internal
 */
class Deployment extends Entity {

    /**
     * @throws ValidationException
     */
    public static function Prepare(DeploymentSpecification $spec, string $namespace, int $workspaceId, string $name, string $version): ?Deployment {
        // Validate input
        if (strlen($namespace) == 0) {
            throw new ValidationException("Namespace missing");
        }
        if (strlen($name) == 0) {
            throw new ValidationException("Name missing");
        }
        /** @var Deployment $exists */
        $exists = (new DeploymentModel())
            ->where('namespace', $namespace)
            ->where('name', $name)
            ->find();
        if ($exists->exists()) {
            throw new ValidationException("Deployment already exists");
        }

        if ($spec->container_image_id && !$spec->container_image->exists()) {
            $spec->container_image->find();
        }

        $item = new Deployment();
        $item->deployment_specification_id = $spec->id;
        $item->namespace = $namespace;
        $item->workspace_id = $workspaceId > 0 ? $workspaceId : null;
        $item->name = $name;
        if ($spec->container_image->exists()) {
            $item->image = $spec->container_image->url;
            $item->image_pull_policy = $spec->container_image->default_image_pull_policy;
        }
        $item->status = \DeploymentStatusTypes::Draft;
        $item->version = $version;
        $item->replicas = 1;
        return $item;
    }

    public function updateVersion(string $value, bool $applyToKubernetes = true): void {
        $prevVersion = $this->version;

        $this->version = $value;
        $this->last_updated = date('Y-m-d H:i:s');
        $this->save();

        if ($applyToKubernetes) {
            DeploymentStepHelper::EmitTrigger(
                DeploymentStepTriggers::Deployment_Version_Updated,
                $this,
                "4spacesdk/kubernetes-service-orchestrator, version {$prevVersion} -> {$this->version} [{$this->last_updated}]"
            );
        }
    }

    public function updateImagePullPolicy(string $value): void {
        $this->image_pull_policy = $value;
        $this->save();

        DeploymentStepHelper::EmitTrigger(DeploymentStepTriggers::Deployment_ImagePullPolicy_Updated, $this);
    }

    public function updateEnvironment(string $value): void {
        $this->environment = $value;
        $this->save();

        DeploymentStepHelper::EmitTrigger(DeploymentStepTriggers::Deployment_Environment_Updated, $this);
    }

    public function updateWorkspaceId(int $value): void {
        $this->workspace_id = $value;
        $this->save();
        $this->workspace = (new WorkspaceModel())->find($this->workspace_id);
    }

    public function updateDatabaseServiceId(int $value): void {
        $this->database_service_id = $value;
        $this->save();
    }

    public function updateResourceManagement(int $cpuLimit, int $cpuRequest,
                                             int $memoryLimit, int $memoryRequest,
                                             int $replicas): void {
        $this->cpu_limit = $cpuLimit;
        $this->cpu_request = $cpuRequest;
        $this->memory_limit = $memoryLimit;
        $this->memory_request = $memoryRequest;
        $this->replicas = $replicas;
        $this->save();

        DeploymentStepHelper::EmitTrigger(DeploymentStepTriggers::Deployment_ResourceManagement_Updated, $this);
    }

    public function updateUpdateManagement(bool $enabled, string $tagRegex, bool $requireApproval): void {
        $this->auto_update_enabled = $enabled;
        $this->auto_update_tag_regex = $tagRegex;
        $this->auto_update_require_approval = $requireApproval;
        $this->save();

        DeploymentStepHelper::EmitTrigger(DeploymentStepTriggers::Deployment_UpdateManagement_Updated, $this);
    }

    public function updateEnvironmentVariables(EnvironmentVariable $values): void {
        $this->environment_variables->find()->deleteAll();
        $this->save($values);
        $this->environment_variables = $values;

        DeploymentStepHelper::EmitTrigger(DeploymentStepTriggers::Deployment_EnvironmentVariable_Updated, $this);
    }

    public function updateEnvironmentVariable(EnvironmentVariable $value, bool $override): void {
        /** @var EnvironmentVariable $environmentVariable */
        $environmentVariable = (new EnvironmentVariableModel())
            ->where('deployment_id', $this->id)
            ->where('name', $value->name)
            ->find();
        if ($environmentVariable->exists() && !$override) {
            return;
        }
        $environmentVariable->deployment_id = $this->id;
        $environmentVariable->name = $value->name;
        $environmentVariable->value = $value->value;
        $environmentVariable->save();

        DeploymentStepHelper::EmitTrigger(DeploymentStepTriggers::Deployment_EnvironmentVariable_Updated, $this);
    }

    public function updateDeploymentVolumes(DeploymentVolume $values): void {
        $this->deployment_volumes->find()->deleteAll();
        $this->save($values);
        $this->deployment_volumes = $values;

        DeploymentStepHelper::EmitTrigger(DeploymentStepTriggers::Deployment_Volume_Updated, $this);
    }

    public function updateStatus(string $value): void {
        if ($this->status != $value) {
            $this->status = $value;
            $this->save();

            ZMQProxy::getInstance()->send(
                Events::Deployment_Changed_Status($this->id),
                (new ChangeEvent(null, $this->toArray()))->toArray()
            );

            if ($this->workspace_id) {
                /** @var Workspace $workspace */
                $workspace = (new WorkspaceModel())
                    ->where('id', $this->workspace_id)
                    ->find();
                $workspace->deployments->find();
                ZMQProxy::getInstance()->send(
                    Events::Workspace_Changed_Status($this->workspace_id),
                    (new ChangeEvent(null, $workspace->toArray()))->toArray()
                );
            }
        }
    }

    public function updateLabels(Label $values): void {
        $this->labels->find()->deleteAll();
        $this->save($values);
        $this->labels = $values;
    }

    public function updateCronJobs(DeploymentCronJob $values): void {
        $this->deployment_cron_jobs->find()->deleteAll();
        $this->save($values);
        $this->deployment_cron_jobs = $values;
    }

    public function getInternalUrl(): string {
        $spec = $this->findDeploymentSpecification();
        return "{$this->name}.{$this->namespace}{$spec->domain_suffix}";
    }

    public function getUrl(bool $includeTls = false, bool $includeSuffix = false): string {
        if ($this->workspace_id && !$this->workspace->exists()) {
            $this->workspace->find();
        }
        /** @var Domain $domain */
        $domain = (new DomainModel())
            ->whereRelated([WorkspaceModel::class, DeploymentModel::class], 'id', $this->id)
            ->find();
        return $this->findDeploymentSpecification()->getUrl($this->workspace->subdomain, $domain, $includeTls, $includeSuffix);
    }

    public function checkStatus(): void {
        $spec = $this->findDeploymentSpecification();
        $steps = $spec->getDeploymentSteps($this);

        $hasInvalidStep = false;
        foreach ($steps as $step) {
            if ($validationError = $step->validateDeployCommand($this)) {
                Data::debug('failed at', get_class($step), $validationError);
                $hasInvalidStep = true;
                break;
            }
        }

        if ($hasInvalidStep) {
            $this->updateStatus(\DeploymentStatusTypes::Draft);
            return;
        }

        $hasFailedStep = false;
        foreach ($steps as $step) {
            $stepStatus = $step->getStatus($this);
            $stepStatus = is_array($stepStatus) ? $stepStatus : [$stepStatus];
            $successStatus = $step->getSuccessStatus($this);
            $hasNonSuccessStatus = count(array_filter($stepStatus, fn($status) => $status !== $successStatus)) > 0;
            if ($hasNonSuccessStatus) {
                $hasFailedStep = true;
                break;
            }
        }

        if ($hasFailedStep) {
            $this->updateStatus(\DeploymentStatusTypes::Deploying);
            return;
        }

        $this->updateStatus(\DeploymentStatusTypes::Active);
    }

    public function deployAllSteps(): ?string {
        $steps = $this->findDeploymentSpecification()->getDeploymentSteps($this);
        $allErrors = [];
        foreach ($steps as $step) {
            $errors = $step->tryExecuteDeployCommand($this);
            if ($errors) {
                $allErrors[] = "<strong>{$step->getName()}</strong>: {$errors}";
            }
        }
        $this->checkStatus();

        ZMQProxy::getInstance()->send(
            Events::Deployment_Deployed(),
            (new ChangeEvent(null, $this->getClone()->toArray()))->toArray()
        );

        return count($allErrors) ? implode("\n", $allErrors) : null;
    }

    public function terminateAllSteps(): ?string {
        $steps = $this->findDeploymentSpecification()->getDeploymentSteps($this);
        $allErrors = [];
        foreach ($steps as $step) {
            $errors = $step->tryExecuteTerminateCommand($this);
            if ($errors) {
                $allErrors[] = "<strong>{$step->getName()}</strong>: {$errors}";
            }
        }
        $this->checkStatus();

        ZMQProxy::getInstance()->send(
            Events::Deployment_Terminated(),
            (new ChangeEvent(null, $this->getClone()->toArray()))->toArray()
        );

        return count($allErrors) ? implode("\n", $allErrors) : null;
    }

    public function findDeploymentSpecification(): DeploymentSpecification {
        if (!$this->deployment_specification->exists() && $this->deployment_specification_id) {
            $this->deployment_specification->find();
        }
        return $this->deployment_specification;
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);

        if (isset($this->url_external)) {
            $item['url_external'] = $this->url_external;
        }
        if (isset($this->url_internal)) {
            $item['url_internal'] = $this->url_internal;
        }

        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Deployment[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

    /**
     * @param $id
     * @return null|Deployment
     */
    public function getById($id) {
        return parent::getById($id);
    }

}
