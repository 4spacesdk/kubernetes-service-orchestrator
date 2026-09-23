<?php namespace App\Entities;

use App\Exceptions\ValidationException;
use App\Libraries\Kubernetes\DnsLabel;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use App\Models\DeploymentModel;
use App\Models\WorkspaceTemplateDeploymentSpecificationModel;
use App\Models\WorkspaceTemplateEnvironmentVariableModel;
use App\Models\KNativeMinScaleScheduleModel;
use App\Models\WorkspaceModel;
use DebugTool\Data;
use Google\ApiCore\ApiException;
use App\Core\Entity;

/**
 * Class Workspace
 * @package App\Entities
 * @property string $type
 * @property int $workspace_template_id
 * @property WorkspaceTemplate $workspace_template
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
 * @property int $project_id the project it is in, or none - see Project
 * @property Project $project
 * @property string $status
 * @property bool $is_paused
 *
 * # Runtime health - the worst of the deployments', see `updateHealth()`
 * @property string $health
 * @property int $health_severity
 * @property string $health_reason
 * @property string $health_changed_at
 *
 * Many
 * @property Deployment $deployments
 * @property Label $labels
 */
class Workspace extends Entity {

    /** Worked out by kso every minute - see `Libraries/Health`. */
    public const array AuditIgnoredFields = ['health', 'health_severity', 'health_reason', 'health_changed_at'];

    /**
     * @throws ValidationException
     * @throws ApiException
     * @throws \Google\ApiCore\ValidationException
     */
    public static function Create(WorkspaceTemplate $workspaceTemplate, string $name, string $namespace, int $domainId, string $subdomain): ?Workspace {
        if (!$workspaceTemplate->exists()) {
            throw new ValidationException("Invalid workspace template");
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
        $item->workspace_template_id = $workspaceTemplate->id;
        $item->workspace_template = $workspaceTemplate;
        $item->name_readable = $name;

        // "Øster" used to become "ster": strtolower() does not know Ø, and what it left was
        // stripped.
        $item->name_system = DnsLabel::from($name);

        $item->namespace = $namespace;
        $item->domain_id = $domainId;
        $item->subdomain = $subdomain;
        $item->email_service_id = $workspaceTemplate->default_email_service_id;
        $item->database_service_id = $workspaceTemplate->default_database_service_id;
        // A workspace lands in the project its template is for.
        $item->project_id = $workspaceTemplate->project_id ?: null;

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
        $workspaceTemplate->labels->find();
        foreach ($workspaceTemplate->labels as $label) {
            $newLabel = Label::Create($label->name, $label->value);
            $newLabel->save($item);
        }

        // Create deployments
        /** @var WorkspaceTemplateDeploymentSpecification $workspaceTemplateDeploymentSpecifications */
        $workspaceTemplateDeploymentSpecifications = (new WorkspaceTemplateDeploymentSpecificationModel())
            ->where('workspace_template_id', $workspaceTemplate->id)
            ->find();
        foreach ($workspaceTemplateDeploymentSpecifications as $workspaceTemplateDeploymentSpecification) {
            $deployment = $item->createDeploymentFromTemplate($workspaceTemplateDeploymentSpecification);
            $item->deployments->add($deployment);
        }

        Publisher::getInstance()->send(
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
        // Check if this spec is part of the workspace's template
        /** @var WorkspaceTemplateDeploymentSpecification $workspaceTemplateDeploymentSpecification */
        $workspaceTemplateDeploymentSpecification = (new WorkspaceTemplateDeploymentSpecificationModel())
            ->where('workspace_template_id', $this->workspace_template_id)
            ->where('deployment_specification_id', $deploymentSpecification->id)
            ->find();
        if ($workspaceTemplateDeploymentSpecification->exists()) {
            return $this->createDeploymentFromTemplate($workspaceTemplateDeploymentSpecification, $name, $version);
        } else {
            $deployment = $this->prepareDeploymentFromSpecification($deploymentSpecification, $name, $version);
            $deployment->save();
            return $deployment;
        }
    }

    /**
     * @param WorkspaceTemplateDeploymentSpecification $workspaceTemplateDeploymentSpecification
     * @return Deployment
     * @throws ValidationException
     * @throws ApiException
     * @throws \Google\ApiCore\ValidationException
     */
    public function createDeploymentFromTemplate(WorkspaceTemplateDeploymentSpecification $workspaceTemplateDeploymentSpecification, ?string $name = null, ?string $version = null): Deployment {
        if (!$workspaceTemplateDeploymentSpecification->deployment_specification->exists()) {
            $workspaceTemplateDeploymentSpecification->deployment_specification->find();
        }
        $deploymentSpecification = $workspaceTemplateDeploymentSpecification->deployment_specification;

        $deployment = $this->prepareDeploymentFromSpecification($deploymentSpecification, $name);

        switch ($deploymentSpecification->workload_type) {
            default:
                if ($version) {
                    $deployment->version = $version;
                } else if (strlen($workspaceTemplateDeploymentSpecification->default_version)) {
                    $deployment->version = $workspaceTemplateDeploymentSpecification->default_version;
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
                $deployment->auto_update_enabled = $workspaceTemplateDeploymentSpecification->default_auto_update_enabled;
                $deployment->auto_update_tag_regex = $workspaceTemplateDeploymentSpecification->default_auto_update_tag_regex;
                $deployment->auto_update_require_approval = $workspaceTemplateDeploymentSpecification->default_auto_update_require_approval;
                $deployment->environment = $workspaceTemplateDeploymentSpecification->default_environment;

                $deployment->cpu_request = $workspaceTemplateDeploymentSpecification->default_cpu_request;
                $deployment->cpu_limit = $workspaceTemplateDeploymentSpecification->default_cpu_limit;
                $deployment->memory_request = $workspaceTemplateDeploymentSpecification->default_memory_request;
                $deployment->memory_limit = $workspaceTemplateDeploymentSpecification->default_memory_limit;
                $deployment->replicas = $workspaceTemplateDeploymentSpecification->default_replicas;
                $deployment->knative_concurrency_limit_soft = $workspaceTemplateDeploymentSpecification->default_knative_concurrency_limit_soft;
                $deployment->knative_concurrency_limit_hard = $workspaceTemplateDeploymentSpecification->default_knative_concurrency_limit_hard;
                $deployment->knative_scheduled_minscale_is_enabled = $workspaceTemplateDeploymentSpecification->default_knative_scheduled_minscale_is_enabled;
                break;
            case \WorkloadTypes::CustomResource:
                break;
        }

        $deployment->save();

        // Copy environment variables from workspace template to deployment
        /** @var WorkspaceTemplateEnvironmentVariable $workspaceTemplateEnvironmentVariables */
        $workspaceTemplateEnvironmentVariables = (new WorkspaceTemplateEnvironmentVariableModel())
            ->where('workspace_template_id', $workspaceTemplateDeploymentSpecification->workspace_template_id)
            ->find();
        $values = new EnvironmentVariable();
        $values->all = array_map(
            fn(WorkspaceTemplateEnvironmentVariable $workspaceTemplateEnvironmentVariable) => EnvironmentVariable::Create(
                $workspaceTemplateEnvironmentVariable->name,
                $workspaceTemplateEnvironmentVariable->value,
                (bool) $workspaceTemplateEnvironmentVariable->is_secret
            ),
            $workspaceTemplateEnvironmentVariables->all ?? []
        );
        $deployment->save($values);

        // Create labels
        $deploymentSpecification->labels->find();
        foreach ($deploymentSpecification->labels as $label) {
            $newLabel = Label::Create($label->name, $label->value);
            $newLabel->save($deployment);
        }

        if ($deployment->knative_scheduled_minscale_is_enabled) {
            if (!$workspaceTemplateDeploymentSpecification->k_native_min_scale_schedules->exists()) {
                $workspaceTemplateDeploymentSpecification->k_native_min_scale_schedules->find();
            }
            foreach ($workspaceTemplateDeploymentSpecification->k_native_min_scale_schedules as $kNativeMinScaleSchedule) {
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

    /**
     * Moves the workspace to another project, or out of any with null. Nothing in the cluster
     * changes: a project is how kso divides workspaces up, not something deployed.
     */
    public function updateProjectId(?int $value): void {
        $this->project_id = $value ?: null;
        $this->save();
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
        // fall off: a workspace with no deployments went back to Draft, and one deployment
        // out of sync made the whole workspace out of sync - a status auto update acts on.
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

        $anyOutOfSync = false;
        $allSynced = true;
        $anyDraft = false;

        foreach ($deployments as $deployment) {
            if ($deployment->status == \DeploymentStatusTypes::OutOfSync) {
                $anyOutOfSync = true;
            }
            if ($deployment->status != \DeploymentStatusTypes::Synced) {
                $allSynced = false;
            }
            if ($deployment->status == \DeploymentStatusTypes::Draft) {
                $anyDraft = true;
            }
            if ($deployment->status == \DeploymentStatusTypes::Inactive) {
                $anyDraft = true;
            }
        }

        if ($deployments->count() == 0) {
            $allSynced = false;
        }

        if ($anyOutOfSync) {
            $newStatus = \WorkspaceStatusTypes::OutOfSync;
        } else if ($allSynced) {
            $newStatus = \WorkspaceStatusTypes::Synced;
        } else if ($oldStatus == \WorkspaceStatusTypes::Inactive && ($anyDraft || $deployments->count() == 0)) {
            // A workspace that was switched off stays switched off until something is
            // actually running in it again. The `count() == 0` half is what `terminate()`
            // needs: it writes Inactive and then calls this to reflect what happened to the
            // deployments, and a workspace with none fell through every branch below to the
            // Draft this starts as - so the status the endpoint wrote was not the status the
            // caller ended up with.
            //
            // A workspace that was never deployed still becomes Draft, because its old
            // status is Draft rather than Inactive.
            $newStatus = \WorkspaceStatusTypes::Inactive;
        } else if ($anyDraft) {
            $newStatus = \WorkspaceStatusTypes::Draft;
        }

        if ($oldStatus != $newStatus) {
            $this->updateStatus($newStatus);
        }
    }

    /**
     * The worst health among the deployments, and which of them it comes from - "api:
     * CrashLoopBackOff (…)". A deployment with no health (Draft, a custom resource) has no say.
     * Only the ones at the worst are named, and none when that is Healthy or Suspended.
     */
    public function updateHealth(int $now): void {
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();

        $healths = [];
        foreach ($deployments as $deployment) {
            $healths[] = $deployment->health;
        }
        $health = \HealthStatusTypes::Worst(...$healths);

        $reasons = [];
        if ($health !== null && \HealthStatusTypes::Severity($health) > \HealthStatusTypes::Severity(\HealthStatusTypes::Healthy)) {
            foreach ($deployments as $deployment) {
                if ($deployment->health === $health) {
                    $reasons[] = $deployment->name . ': ' . ($deployment->health_reason ?: $deployment->health);
                }
            }
        }
        $reason = mb_strimwidth(implode('; ', $reasons), 0, 1000, '…');

        $healthChanged = $this->health !== $health;
        if (!$healthChanged && ($this->health_reason ?? '') === $reason) {
            return;
        }

        if ($healthChanged) {
            $this->health = $health;
            $this->health_severity = \HealthStatusTypes::Severity($health);
            $this->health_changed_at = $health === null ? null : date('Y-m-d H:i:s', $now);
        }
        $this->health_reason = $reason;
        $this->save();

        Publisher::getInstance()->send(
            Events::Workspace_Changed_Health($this->id),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
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

        Publisher::getInstance()->send(
            Events::Workspace_Changed_Status($this->id),
            (new ChangeEvent(null, $next))->toArray()
        );
    }

    public function deploy(): ?string {
        $this->status = \WorkspaceStatusTypes::OutOfSync;
        $this->save();

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();
        $allErrors = [];
        foreach ($deployments as $deployment) {
            $errors = $deployment->deployAllSteps();
            if ($errors) {
                $allErrors[] = "{$deployment->name}\n{$errors}";
            }
        }
        $this->deployments = $deployments;

        Publisher::getInstance()->send(
            Events::Workspace_Deployed(),
            (new ChangeEvent(null, $this->getClone()->toArray()))->toArray()
        );

        $this->checkStatus();

        return count($allErrors) ? implode("\n\n", $allErrors) : null;
    }

    /**
     * Pause the workspace: terminate it and remember that a person decided to.
     *
     * The status is recomputed from the deployments, so it cannot hold a pause - a
     * workspace with none goes back to Draft on its own, and one deployment out of sync makes
     * the whole workspace Out of sync, which auto update does *not* skip. The flag is what the
     * lists, auto update and the notifications go by.
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
                $allErrors[] = "{$deployment->name}\n{$errors}";
            }

            $deployment->updateStatus(\DeploymentStatusTypes::Inactive, false);
        }
        $this->deployments = $deployments;

        Publisher::getInstance()->send(
            Events::Workspace_Terminated(),
            (new ChangeEvent(null, $this->getClone()->toArray()))->toArray()
        );

        $this->checkStatus();

        return count($allErrors) ? implode("\n\n", $allErrors) : null;
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
                Publisher::getInstance()->send(
                    Events::Workspace_Updated(),
                    (new ChangeEvent($original, $this->toArray()))->toArray()
                );
            }
        }
    }

    public function delete($related = null) {
        parent::delete($related);

        if ($related === null) {
            Publisher::getInstance()->send(
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
