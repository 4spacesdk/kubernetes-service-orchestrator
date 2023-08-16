<?php namespace App\Entities;

use App\Exceptions\ValidationException;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\GoogleCloud\GoogleCloudArtifactRegistry;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\ConfigurationSpecificationModel;
use App\Models\DeploymentModel;
use App\Models\DeploymentPackageDeploymentSpecificationModel;
use App\Models\WorkspaceModel;
use Google\ApiCore\ApiException;
use RestExtension\Core\Entity;

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
 *
 * Many
 * @property Deployment $deployments
 */
class Workspace extends Entity {

    /**
     * @throws ValidationException
     */
    public static function Create(DeploymentPackage $deploymentPackage, string $name, int $domainId, string $subdomain): ?Workspace {
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

        $name = strtolower($name); // To lowercase
        $name = str_replace(' ', '_', $name); // Replace space with _
        $name = str_replace(',', '', $name); // Remove ,
        $name = preg_replace("/[^A-Za-z0-9_]/", '', $name); // Remove all non-alphabetic
        $name = str_replace('_', '-', $name); // Replace _ with -
        $item->name_system = $name;

        $item->namespace = substr("{$deploymentPackage->namespace}-{$item->name_system}", 0, 63);
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
     * @param DeploymentPackageDeploymentSpecification $deploymentPackageDeploymentSpecification
     * @return Deployment
     * @throws ValidationException
     * @throws ApiException
     * @throws \Google\ApiCore\ValidationException
     */
    public function createDeploymentFromPackage(DeploymentPackageDeploymentSpecification $deploymentPackageDeploymentSpecification): Deployment {
        if (!$deploymentPackageDeploymentSpecification->deployment_specification->exists()) {
            $deploymentPackageDeploymentSpecification->deployment_specification->find();
        }
        $deploymentSpecification = $deploymentPackageDeploymentSpecification->deployment_specification;

        $deployment = $this->prepareDeploymentFromSpecification($deploymentSpecification);

        if (!$this->deployment_package->exists()) {
            $this->deployment_package->find();
        }

        if (strlen($deploymentPackageDeploymentSpecification->default_version)) {
            $deployment->version = $deploymentPackageDeploymentSpecification->default_version;
        } else {
            // Find newest version
            if (!$deploymentSpecification->container_image->exists()) {
                $deploymentSpecification->container_image->find();
            }
            $registry = new GoogleCloudArtifactRegistry($deploymentSpecification->container_image->url);
            $tags = $registry->getTags();
            $tags = array_filter($tags, fn($tag) => !str_contains($tag, 'latest'));
            $deployment->version = end($tags);
        }

        $deployment->keel_policy = $deploymentPackageDeploymentSpecification->default_keel_policy;
        $deployment->keel_auto_update = $deploymentPackageDeploymentSpecification->default_keel_auto_update;
        $deployment->environment = $deploymentPackageDeploymentSpecification->default_environment;
        $deployment->enable_podio_notification = $deploymentPackageDeploymentSpecification->default_enable_podio_notification;

        $deployment->cpu_request = $deploymentPackageDeploymentSpecification->default_cpu_request;
        $deployment->cpu_limit = $deploymentPackageDeploymentSpecification->default_cpu_limit;
        $deployment->memory_request = $deploymentPackageDeploymentSpecification->default_memory_request;
        $deployment->memory_limit = $deploymentPackageDeploymentSpecification->default_memory_limit;
        $deployment->replicas = $deploymentPackageDeploymentSpecification->default_replicas;

        $deployment->save();

        return $deployment;
    }

    /**
     * @throws ValidationException
     */
    public function prepareDeploymentFromSpecification(DeploymentSpecification $specification): Deployment {
        $deployment = Deployment::Prepare(
            $specification,
            $this->namespace,
            $specification->name
        );
        $deployment->workspace_id = $this->id;

        if ($specification->enable_database) {
            $deployment->database_service_id = $this->database_service_id;
        }
        if ($specification->enable_ingress) {
            $deployment->domain_id = $this->domain_id;
            $deployment->subdomain = $this->subdomain;
            $deployment->aliases = $this->aliases;
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
        $this->aliases = $aliases;
        $this->save();

        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();
        foreach ($deployments as $deployment) {
            $deployment->updateIngress($domainId, $subdomain, $aliases);
        }
    }

    public function checkStatus(): void {
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $this->id)
            ->find();
        foreach ($deployments as $deployment) {
            $deployment->checkStatus();
        }
        $this->deployments = $deployments;
    }

    public function deploy(): ?string {
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
        return count($allErrors) ? implode("\n", $allErrors) : null;
    }

    public function terminate(): ?string {
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
        }
        $this->deployments = $deployments;
        return count($allErrors) ? implode("\n", $allErrors) : null;
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
    public function getIterator() {
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
