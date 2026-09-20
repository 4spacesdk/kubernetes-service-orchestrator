<?php namespace App\Entities;

use App\Exceptions\ValidationException;
use App\Libraries\Kubernetes\DnsLabel;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\DeploymentModel;
use App\Models\DeploymentPackageDeploymentSpecificationModel;
use App\Models\DeploymentPackageEnvironmentVariableModel;
use App\Models\KNativeMinScaleScheduleModel;
use App\Models\WorkspaceModel;
use DebugTool\Data;
use Google\ApiCore\ApiException;
use App\Core\Entity;

/**
 * Class Workspace
 * @package App\Entities
 * @property string $type
 * @property int $deployment_package_id
 * @property DeploymentPackage $deployment_package
 * @property string $name_readable
 * @property string $name_system
 * @property string $namespace
 * @property int $email_service_id
 * @property EmailService $email_service
 * @property int $domain_id
 * @property Domain $domain
 * @property string $subdomain
 * @property string $aliases
 * @property int $database_service_id
 * @property DatabaseService $database_service
 * @property string $status
 * @property bool $is_paused
 *
 * Many
 * @property Deployment $deployments
 * @property Label $labels
 */
class Workspace extends Entity {

    /**
     * @throws ValidationException
     * @throws ApiException
     * @throws \Google\ApiCore\ValidationException
     */
    public static function Create(DeploymentPackage $deploymentPackage, string $name, string $namespace, int $domainId, string $subdomain): ?Workspace {
        if (!$deploymentPackage->exists()) {
            throw new ValidationException("Invalid deployment package");
        }
        if (strlen($name) == 0) {
            throw new ValidationException("Name missing");
        }
        if (strlen($domainId) == 0) {
            throw new ValidationException("Domain missing");
        }
        $domain = new Domain();
        $domain->find($domainId);
        if (!$domain->exists()) {
            throw new ValidationException("Domain not found");
        }
        if (strlen($subdomain) == 0) {
            throw new ValidationException("Subdomain missing");
        }
        // Both end up as names in the cluster. Refused here rather than by Kubernetes on the
        // first deploy, after the workspace and its deployments exist.
        if (!DnsLabel::isValid($namespace)) {
            throw new ValidationException("Invalid namespace '{$namespace}': at most 63 of a-z, 0-9 and -, starting and ending with a letter or digit");
        }
        if (!DnsLabel::isValid($subdomain)) {
            throw new ValidationException("Invalid subdomain '{$subdomain}': at most 63 of a-z, 0-9 and -, starting and ending with a letter or digit");
        }
        /** @var Workspace $subdomainInUse */
        $subdomainInUse = (new WorkspaceModel())
            ->where('domain_id', $domain->id)
            ->where('subdomain', $subdomain)
            ->find();
        if ($subdomainInUse->exists()) {
            throw new ValidationException("Domain and subdomain already used");
        }

        $item = new Workspace();
        $item->deployment_package_id = $deploymentPackage->id;
        $item->deployment_package = $deploymentPackage;
        $item->name_readable = $name;

        // "Øster" used to become "ster": strtolower() does not know Ø, and what it left was
        // stripped.
        $item->name_system = DnsLabel::from($name);

        $item->namespace = $namespace;
        $item->domain_id = $domainId;
        $item->subdomain = $subdomain;
        $item->email_service_id = $deploymentPackage->default_email_service_id;
        $item->database_service_id = $deploymentPackage->default_database_service_id;

        /** @var Workspace $workspaceNameInUse */
        $workspaceNameInUse = (new WorkspaceModel())
            ->where('namespace', $item->namespace)
            ->where('name_system', $item->name_system)
            ->find();
        if ($workspaceNameInUse->exists()) {
            throw new ValidationException("Name already used");
        }

        $item->save();

        // Create labels
        $deploymentPackage->labels->find();
        foreach ($deploymentPackage->labels as $label) {
            $newLabel = Label::Create($label->name, $label->value);
            $newLabel->save($item);
        }

        // Create deployments
        /** @var DeploymentPackageDeploymentSpecification $deploymentPackageDeploymentSpecifications */
        $deploymentPackageDeploymentSpecifications = (new DeploymentPackageDeploymentSpecificationModel())
            ->where('deployment_package_id', $deploymentPackage->id)
            ->find();
        foreach ($deploymentPackageDeploymentSpecifications as $deploymentPackageDeploymentSpecification) {
            $deployment = $item->createDeploymentFromPackage($deploymentPackageDeploymentSpecification);
            $item->deployments->add($deployment);
        }

        ZMQProxy::getInstance()->send(
            Events::Workspace_Created(),
            (new ChangeEvent(null, $item->toArray()))->toArray()
        );

        return $item;
    }

    /**
     * @throws ValidationException
     * @throws \Google\ApiCore\ValidationException
     * @throws ApiException
     */
    public function addDeployment(DeploymentSpecification $deploymentSpecification, ?string $name, ?string $version): ?Deployment {
        // Check if this spec is part of the workspace deployment package
        /** @var DeploymentPackageDeploymentSpecification $deploymentPackageDeploymentSpecification */
        $deploymentPackageDeploymentSpecification = (new DeploymentPackageDeploymentSpecificationModel())
            ->where('deployment_package_id', $this->deployment_package_id)
            ->where('deployment_specification_id', $deploymentSpecification->id)
            ->find();
        if ($deploymentPackageDeploymentSpecification->exists()) {
            return $this->createDeploymentFromPackage($deploymentPackageDeploymentSpecification, $name, $version);
        } else {
            $deployment = $this->prepareDeploymentFromSpecification($deploymentSpecification, $name, $version);
            $deployment->save();
            return $deployment;
        }
    }

    /**
     * @param DeploymentPackageDeploymentSpecification $deploymentPackageDeploymentSpecification
     * @return Deployment
     * @throws ValidationException
     * @throws ApiException
     * @throws \Google\ApiCore\ValidationException
     */
    public function createDeploymentFromPackage(DeploymentPackageDeploymentSpecification $deploymentPackageDeploymentSpecification, ?string $name = null, ?string $version = null): Deployment {
        if (!$deploymentPackageDeploymentSpecification->deployment_specification->exists()) {
            $deploymentPackageDeploymentSpecification->deployment_specification->find();
        }
        $deploymentSpecification = $deploymentPackageDeploymentSpecification->deployment_specification;

        $deployment = $this->prepareDeploymentFromSpecification($deploymentSpecification, $name);

        switch ($deploymentSpecification->workload_type) {
            default:
                if ($version) {
                    $deployment->version = $version;
                } else if (strlen($deploymentPackageDeploymentSpecification->default_version)) {
                    $deployment->version = $deploymentPackageDeploymentSpecification->default_version;
                } else  {
                    // Find newest version
                    if (!$deploymentSpecification->container_image->exists()) {
                        $deploymentSpecification->container_image->find();
                    }
                    try {
                        $tags = $deploymentSpecification->container_image->getTags();
                    } catch (\Throwable $e) {
                        // A registry that cannot be read gives no version, as it did before a failed lookup threw.
                        Data::debug($e->getMessage());
                        $tags = [];
                    }
                    $tags = array_filter($tags, fn($tag) => !str_contains($tag, 'latest'));
                    $deployment->version = end($tags);
                }
                $deployment->auto_update_enabled = $deploymentPackageDeploymentSpecification->default_auto_update_enabled;
                $deployment->auto_update_tag_regex = $deploymentPackageDeploymentSpecification->default_auto_update_tag_regex;
                $deployment->auto_update_require_approval = $deploymentPackageDeploymentSpecification->default_auto_update_require_approval;
                $deployment->environment = $deploymentPackageDeploymentSpecification->default_environment;

                $deployment->cpu_request = $deploymentPackageDeploymentSpecification->default_cpu_request;
                $deployment->cpu_limit = $deploymentPackageDeploymentSpecification->default_cpu_limit;
                $deployment->memory_request = $deploymentPackageDeploymentSpecification->default_memory_request;
                $deployment->memory_limit = $deploymentPackageDeploymentSpecification->default_memory_limit;
                $deployment->replicas = $deploymentPackageDeploymentSpecification->default_replicas;
                $deployment->knative_concurrency_limit_soft = $deploymentPackageDeploymentSpecification->default_knative_concurrency_limit_soft;
                $deployment->knative_concurrency_limit_hard = $deploymentPackageDeploymentSpecification->default_knative_concurrency_limit_hard;
                $deployment->knative_scheduled_minscale_is_enabled = $deploymentPackageDeploymentSpecification->default_knative_scheduled_minscale_is_enabled;
                break;
            case \WorkloadTypes::CustomResource:
                break;
        }

        $deployment->save();

        // Copy environment variables from deployment package to deployment
        /** @var DeploymentPackageEnvironmentVariable $deploymentPackageEnvironmentVariables */
        $deploymentPackageEnvironmentVariables = (new DeploymentPackageEnvironmentVariableModel())
            ->where('deployment_package_id', $deploymentPackageDeploymentSpecification->deployment_package_id)
            ->find();
        $values = new EnvironmentVariable();
        $values->all = array_map(
            fn(DeploymentPackageEnvironmentVariable $deploymentPackageEnvironmentVariable) => EnvironmentVariable::Create(
                $deploymentPackageEnvironmentVariable->name,
                $deploymentPackageEnvironmentVariable->value
            ),
            $deploymentPackageEnvironmentVariables->all ?? []
        );
        $deployment->save($values);

        // Create labels
        $deploymentSpecification->labels->find();
        foreach ($deploymentSpecification->labels as $label) {
            $newLabel = Label::Create($label->name, $label->value);
            $newLabel->save($deployment);
        }

        if ($deployment->knative_scheduled_minscale_is_enabled) {
            if (!$deploymentPackageDeploymentSpecification->k_native_min_scale_schedules->exists()) {
                $deploymentPackageDeploymentSpecification->k_native_min_scale_schedules->find();
            }
            foreach ($deploymentPackageDeploymentSpecification->k_native_min_scale_schedules as $kNativeMinScaleSchedule) {
                $kNativeMinScaleSchedule->id = null;
                $kNativeMinScaleSchedule->save();
                $deployment->save($kNativeMinScaleSchedule);
            }
        }

        return $deployment;
    }

    /**
     * @throws ValidationException
     */
    public function prepareDeploymentFromSpecification(DeploymentSpecification $specification, ?string $name = null, ?string $version = null): Deployment {
        $deployment = Deployment::Prepare(
            $specification,
            $this->namespace,
            $this->id,
            $name ?? $specification->name ?? '',
            $version ?? '',
        );

        if ($specification->enable_database) {
            $deployment->database_service_id = $this->database_service_id;
        }

        return $deployment;
    }

    public function updateName(string $value): void {
        $this->name_readable = $value;
        $this->save();
    }

    public function updateEmailServiceId(int $value): void {
        $this->email_service_id = $value;
        $this->save();

        DeploymentStepHelper::ExecuteWorkspaceDeployCommand($this, [
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
        if (strlen($subdomain) == 0) {
            throw new ValidationException("Subdomain missing");
        }
        /** @var Workspace $subdomainInUse */
        $subdomainInUse = (new WorkspaceModel())
            ->where('domain_id', $domain->id)
            ->where('subdomain', $subdomain)
            ->where('id !=', $this->id)
            ->find();
        if ($subdomainInUse->exists()) {
            throw new ValidationException("Domain and subdomain already used");
        }

        $this->domain_id = $domainId;
        $this->subdomain = $subdomain;
        $this->aliases = implode(',', self::ParseAliases($aliases, $subdomain, $domain));
        $this->save();
    }

    /**
     * Hostnames that redirect to this workspace
     * @return string[]
     */
    public function getAliasHostnames(): array {
        if (!$this->domain->exists()) {
            $this->domain->find();
        }
        $hostnames = [];
        foreach (explode(',', $this->aliases ?? '') as $alias) {
            $alias = strtolower(trim($alias));
            if (strlen($alias) == 0) {
                continue;
            }
            $hostnames[] = self::AliasToHostname($alias, $this->domain);
        }
        return array_values(array_unique($hostnames));
    }

    /**
     * Aliases are comma-separated. Each alias is either a subdomain or a hostname on the domain
     * @return string[]
     * @throws ValidationException
     */
    private static function ParseAliases(string $aliases, string $subdomain, Domain $domain): array {
        $domainName = strtolower($domain->name);
        $primaryHostname = strtolower($subdomain) . '.' . $domainName;
        $result = [];
        foreach (explode(',', $aliases) as $alias) {
            $alias = strtolower(trim($alias));
            if (strlen($alias) == 0) {
                continue;
            }
            if (str_contains($alias, '.') && $alias !== $domainName && !str_ends_with($alias, '.' . $domainName)) {
                throw new ValidationException("Alias \"{$alias}\" must be a subdomain or a hostname on {$domainName}");
            }
            $hostname = self::AliasToHostname($alias, $domain);
            if (!preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]+$/', $hostname)) {
                throw new ValidationException("Alias \"{$alias}\" is not a valid hostname");
            }
            if ($hostname === $primaryHostname) {
                throw new ValidationException("Alias \"{$alias}\" is the same as the workspace hostname");
            }
            if (!in_array($alias, $result)) {
                $result[] = $alias;
            }
        }
        return $result;
    }

    private static function AliasToHostname(string $alias, Domain $domain): string {
        $domainName = strtolower($domain->name);
        if ($alias === $domainName || str_ends_with($alias, '.' . $domainName)) {
            return $alias;
        }
        return $alias . '.' . $domainName;
    }

    public function updateLabels(Label $values): void {
        $this->labels->find()->deleteAll();
        $this->save($values);
        $this->labels = $values;
    }

    public function checkStatus(): void {
        if (!$this->exists()) {
            return;
        }

        // A pause is a decision, not a state to derive. Everything below reads the
        // deployments and writes what they add up to, which is exactly how a pause used to
        // fall off: a workspace with no deployments went back to Draft, one deployment
        // deploying made the whole workspace Deploying, and a failing one made it Error -
        // and Error is a status auto update acts on.
        if ($this->is_paused) {
            if ($this->status != \WorkspaceStatusTypes::Paused) {
                $this->updateStatus(\WorkspaceStatusTypes::Paused);
            }
            return;
        }

        $oldStatus = $this->status;

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();

        $newStatus = \WorkspaceStatusTypes::Draft;

        $hasError = false;
        $hasDeploying = false;
        $allActive = true;
        $anyDraft = false;

        foreach ($deployments as $deployment) {
            if ($deployment->status == \DeploymentStatusTypes::Error) {
                $hasError = true;
            }
            if ($deployment->status == \DeploymentStatusTypes::Deploying) {
                $hasDeploying = true;
            }
            if ($deployment->status == \DeploymentStatusTypes::Active) {
                // Keep allActive true if this is active
            } else {
                $allActive = false;
            }
            if ($deployment->status == \DeploymentStatusTypes::Draft) {
                $anyDraft = true;
            }
            if ($deployment->status == \DeploymentStatusTypes::Inactive) {
                $anyDraft = true;
            }
        }

        if ($deployments->count() == 0) {
            $allActive = false;
        }

        if ($hasError) {
            $newStatus = \WorkspaceStatusTypes::Error;
        } else if ($hasDeploying) {
            $newStatus = \WorkspaceStatusTypes::Deploying;
        } else if ($allActive) {
            $newStatus = \WorkspaceStatusTypes::Active;
        } else if ($oldStatus == \WorkspaceStatusTypes::Inactive && $anyDraft) {
            $newStatus = \WorkspaceStatusTypes::Inactive;
        } else if ($anyDraft) {
            $newStatus = \WorkspaceStatusTypes::Draft;
        }

        if ($oldStatus != $newStatus) {
            $this->updateStatus($newStatus);
        }
    }

    public function updateStatus(string $newStatus): void {
        $this->status = $newStatus;
        $this->save();

        $next = $this->toArray();
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();
        $next['deployments'] = $deployments->allToArray();

        ZMQProxy::getInstance()->send(
            Events::Workspace_Changed_Status($this->id),
            (new ChangeEvent(null, $next))->toArray()
        );
    }

    public function deploy(): ?string {
        $this->status = \WorkspaceStatusTypes::Deploying;
        $this->save();

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();
        $allErrors = [];
        foreach ($deployments as $deployment) {
            $errors = $deployment->deployAllSteps();
            if ($errors) {
                $allErrors[] = "<strong>{$deployment->name}</strong><br>{$errors}<br>";
            }
        }
        $this->deployments = $deployments;

        ZMQProxy::getInstance()->send(
            Events::Workspace_Deployed(),
            (new ChangeEvent(null, $this->getClone()->toArray()))->toArray()
        );

        $this->checkStatus();

        return count($allErrors) ? implode("\n", $allErrors) : null;
    }

    /**
     * Pause the workspace: terminate it and remember that a person decided to.
     *
     * The status is recomputed from the deployments, so it cannot hold a pause - a
     * workspace with none goes back to Draft on its own, one deployment deploying makes the
     * whole workspace Deploying, and a failing one makes it Error, which auto update does
     * *not* skip. The flag is what the lists, auto update and the notifications go by.
     *
     * The workload is shut down, as before. Volumes go with it, by their reclaim policy -
     * that is the part still to be decided before this is offered as anything more.
     */
    public function pause(): ?string {
        $this->is_paused = true;
        $this->save();

        $errors = $this->terminate();
        $this->checkStatus();

        return $errors;
    }

    /**
     * Take the pause off. The workspace is still terminated - deploying it again is a
     * separate decision, and the button for it is right there.
     */
    public function resume(): void {
        $this->is_paused = false;
        // Back to whatever the deployments say, which after a pause is a terminated
        // workspace: deploying it again is the next decision, and its own button.
        $this->status = \WorkspaceStatusTypes::Inactive;
        $this->save();

        $this->checkStatus();
    }

    public function terminate(): ?string {
        $this->updateStatus(\WorkspaceStatusTypes::Inactive);

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();
        $allErrors = [];
        /** @var Deployment $deployment */
        foreach ($deployments as $deployment) {
            $errors = $deployment->terminateAllSteps();
            if ($errors) {
                $allErrors[] = "<strong>{$deployment->name}</strong><br>{$errors}<br>";
            }

            $deployment->updateStatus(\DeploymentStatusTypes::Inactive, false);
        }
        $this->deployments = $deployments;

        ZMQProxy::getInstance()->send(
            Events::Workspace_Terminated(),
            (new ChangeEvent(null, $this->getClone()->toArray()))->toArray()
        );

        $this->checkStatus();

        return count($allErrors) ? implode("\n", $allErrors) : null;
    }

    public function getUrl(): string {
        if (!$this->domain->exists()) {
            $this->domain->find();
        }
        if (strlen($this->subdomain)) {
            return "{$this->subdomain}.{$this->domain->name}";
        } else {
            return $this->domain->name;
        }
    }

    public function save($related = null, $relatedField = null) {
        $isChanged = $this->hasChanged();

        $original = $this->getOriginal();
        Data::debug($isChanged);
        parent::save($related, $relatedField);

        if (is_null($related)) {
            if ($isChanged) {
                ZMQProxy::getInstance()->send(
                    Events::Workspace_Updated(),
                    (new ChangeEvent($original, $this->toArray()))->toArray()
                );
            }
        }
    }

    public function delete($related = null) {
        parent::delete($related);

        if ($related === null) {
            ZMQProxy::getInstance()->send(
                Events::Workspace_Deleted(),
                (new ChangeEvent(null, $this->toArray()))->toArray()
            );
        }
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Workspace[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

    /**
     * @param $id
     * @return Workspace
     */
    public function getById($id) {
        return parent::getById($id);
    }

}
