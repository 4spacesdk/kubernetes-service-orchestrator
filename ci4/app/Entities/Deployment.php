<?php namespace App\Entities;

use App\Core\Entity;
use App\Exceptions\ValidationException;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\DeploymentModel;
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
 * @property int $domain_id
 * @property Domain $domain
 * @property string $subdomain
 * @property string $aliases
 *
 * # Update management
 * @property bool $auto_update_enabled
 * @property string $auto_update_tag_regex
 * @property bool $auto_update_require_approval
 * @property bool $enable_podio_notification
 *
 * # Resource management
 * @property int $cpu_limit
 * @property int $cpu_request
 * @property int $memory_limit
 * @property int $memory_request
 * @property int $replicas
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
 */
class Deployment extends Entity {

    /**
     * @throws ValidationException
     */
    public static function Prepare(DeploymentSpecification $spec, string $namespace, string $name): ?Deployment {
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
        $item->name = $name;
        if ($spec->container_image->exists()) {
            $item->image = $spec->container_image->url;
        }
        $item->custom_resource = $spec->custom_resource;
        $item->status = \DeploymentStatusTypes::Draft;
        return $item;
    }

    public function updateVersion(string $value, bool $applyToKubernetes = true): void {
        $prevVersion = $this->version;

        $this->version = $value;
        $this->last_updated = date('Y-m-d H:i:s');
        $this->save();

        if ($applyToKubernetes) {
            DeploymentStepHelper::ExecuteDeployCommand($this, [
                DeploymentSteps::Deployment,
                DeploymentSteps::Migration,
            ], "4spacesdk/kubernetes-service-orchestrator, version {$prevVersion} -> {$this->version} [{$this->last_updated}]");
        }
    }

    public function updateEnvironment(string $value): void {
        $this->environment = $value;
        $this->save();

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::Deployment,
        ]);
    }

    public function updateDatabaseServiceId(int $value): void {
        $this->database_service_id = $value;
        $this->save();
    }

    /**
     * @throws ValidationException
     */
    public function updateIngress(int $domainId, string $subdomain, string $aliases): void {
        $domain = new Domain();
        $domain->find($domainId);
        if (!$domain->exists()) {
            throw new ValidationException("Domain not found");
        }

        $this->domain_id = $domainId;
        $this->subdomain = $subdomain;
        $this->aliases = $aliases;
        $this->save();

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::Redirects,
        ]);
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

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::Deployment,
        ]);
    }

    public function updateUpdateManagement(bool $enabled, string $tagRegex, bool $requireApproval, bool $enablePodioNotification): void {
        $this->auto_update_enabled = $enabled;
        $this->auto_update_tag_regex = $tagRegex;
        $this->auto_update_require_approval = $requireApproval;
        $this->enable_podio_notification = $enablePodioNotification;
        $this->save();

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::Deployment,
        ]);
    }

    public function updateEnvironmentVariables(EnvironmentVariable $values): void {
        $this->environment_variables->find()->deleteAll();
        $this->save($values);
        $this->environment_variables = $values;

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::Deployment,
        ]);
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

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::Deployment,
        ]);
    }

    public function updateDeploymentVolumes(DeploymentVolume $values): void {
        $this->deployment_volumes->find()->deleteAll();
        $this->save($values);
        $this->deployment_volumes = $values;

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::PersistentVolume,
            DeploymentSteps::PersistentVolumeClaim,
            DeploymentSteps::Deployment,
        ]);
    }

    public function updateCustomResource(string $content): void {
        $this->custom_resource = $content;
        $this->save();

        DeploymentStepHelper::ExecuteDeployCommand($this, [
            DeploymentSteps::CustomResource,
        ]);
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

    public function getInternalUrl(): string {
        if (!$this->domain->exists()) {
            $this->domain->find();
        }

        $spec = $this->findDeploymentSpecification();
        return "{$this->name}.{$this->namespace}{$spec->domain_suffix}";
    }

    public function getUrl(bool $includeTls = false, bool $includeSuffix = false): string {
        if (!$this->domain->exists()) {
            $this->domain->find();
        }

        return $this->findDeploymentSpecification()->getUrl($this->subdomain, $this->domain, $includeTls, $includeSuffix);
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
            if ($step->getStatus($this) != $step->getSuccessStatus($this)) {
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

        if (isset($this->domain) && isset($this->deployment_specification)) {
            $item['url_external'] = $this->getUrl(true, true);
            $item['url_internal'] = $this->getInternalUrl();
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
