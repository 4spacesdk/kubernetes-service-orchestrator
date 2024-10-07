<?php namespace App\Entities;

use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\DeploymentSteps\ClusterRoleBindingStep;
use App\Libraries\DeploymentSteps\ClusterRoleStep;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\CustomResourceStep;
use App\Libraries\DeploymentSteps\DatabaseStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\IngressStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;
use App\Libraries\DeploymentSteps\RedirectsStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\Libraries\DeploymentSteps\ServiceStep;
use App\Models\DeploymentSpecificationEnvironmentVariableModel;
use App\Models\DeploymentSpecificationIngressModel;
use App\Models\DeploymentSpecificationIngressRulePathModel;
use App\Models\DeploymentSpecificationServiceAnnotationModel;
use App\Models\DeploymentSpecificationServicePortModel;
use App\Models\DeploymentVolumeModel;
use RestExtension\Core\Entity;

/**
 * Class DeploymentSpecification
 * @package App\Entities
 *
 * # Mandatory settings
 * @property string $name
 * @property string $type
 * @property int $container_image_id
 * @property ContainerImage $container_image
 *
 * # Enable features
 * @property bool $enable_database
 * @property bool $enable_cronjob
 * @property bool $enable_ingress
 * @property bool $enable_rbac
 * @property bool $enable_volumes
 *
 * # Domain settings
 * @property string $domain_tls
 * @property string $domain_prefix
 * @property string $domain_suffix
 * @property string $domain_aliases
 *
 * # Database Migration settings
 * @property int $database_migration_container_image_id
 * @property ContainerImage $database_migration_container_image
 * @property string $database_migration_container_image_tag_policy
 * @property string $database_migration_container_image_tag_value
 * @property string $database_migration_command
 * @property string $database_migration_verification_type
 * @property string $database_migration_verification_value
 *
 * # Cron Job settings
 * @property string $cronjob_url
 *
 * # Custom Resource
 * @property string $custom_resource
 *
 * Many
 * @property Deployment $deployments
 * @property DeploymentSpecificationPostCommand $deployment_specification_post_commands
 * @property DeploymentSpecificationEnvironmentVariable $deployment_specification_environment_variables
 * @property DeploymentSpecificationServicePort $deployment_specification_service_ports
 * @property DeploymentSpecificationIngress $deployment_specification_ingresses
 * @property DeploymentSpecificationClusterRoleRule $deployment_specification_cluster_role_rules
 * @property DeploymentSpecificationServiceAnnotation $deployment_specification_service_annotations
 * @property DeploymentSpecificationQuickCommand $deployment_specification_quick_commands
 * @property DeploymentSpecificationInitContainer $deployment_specification_init_containers
 * @property DeploymentSpecificationPostUpdateAction $deployment_specification_post_update_actions
 * @property Label $labels
 *
 * @property DeploymentStep $deploymentSteps
 */
class DeploymentSpecification extends Entity {

    /**
     * @param Deployment|null $deployment
     * @return BaseDeploymentStep[]
     */
    public function getDeploymentSteps(?Deployment $deployment = null): array {
        $order = [
            DatabaseStep::class,
            NamespaceStep::class,
            ClusterRoleStep::class,
            ServiceAccountStep::class,
            ClusterRoleBindingStep::class,
            PersistentVolumeStep::class,
            PersistentVolumeClaimStep::class,
            CustomResourceStep::class,
            DeploymentStep::class,
            ServiceStep::class,
            IngressStep::class,
            RedirectsStep::class,
            MigrationJobStep::class,
            CronjobStep::class,
        ];

        $steps = [
            new NamespaceStep(),
        ];

        switch ($this->type) {
            case \DeploymentSpecificationTypes::Deployment:
                $steps[] = new DeploymentStep();
                $steps[] = new ServiceStep();
                break;
            case \DeploymentSpecificationTypes::Custom:
                $steps[] = new CustomResourceStep();
                break;
        }

        if ($this->enable_database) {
            $steps[] = new DatabaseStep();
            $steps[] = new MigrationJobStep();
        }
        if ($this->enable_rbac) {
            $steps[] = new ClusterRoleStep();
            $steps[] = new ServiceAccountStep();
            $steps[] = new ClusterRoleBindingStep();
        }
        if ($this->enable_ingress) {
            $steps[] = new IngressStep();
            $steps[] = new RedirectsStep();
        }
        if ($this->enable_cronjob) {
            $steps[] = new CronjobStep();
        }
        if ($this->enable_volumes && $deployment) {
            /** @var DeploymentVolume $deploymentVolumes */
            $deploymentVolumes = (new DeploymentVolumeModel())
                ->where('deployment_id', $deployment->id)
                ->find();
            if ($deploymentVolumes->exists()) {
                $steps[] = new PersistentVolumeStep();
                $steps[] = new PersistentVolumeClaimStep();
            }
        }

        usort($steps, fn($a, $b) => array_search(get_class($a), $order) - array_search(get_class($b), $order));

        return $steps;
    }

    public function hasDeploymentStep(Deployment $deployment, string $class): bool {
        $allowedSteps = array_map(
            fn(BaseDeploymentStep $step) => get_class($step),
            $this->getDeploymentSteps($deployment)
        );
        return in_array($class, $allowedSteps);
    }

    public function getUrl(string $subdomain, Domain $domain, bool $includeTls = false, bool $includeSuffix = false): string {
        $tls = $includeTls ? "{$this->domain_tls}://" : '';
        $domain = $domain->name;
        if (strlen($subdomain)) {
            $domain = $subdomain . '.' . $domain;
        }
        $suffix = $includeSuffix ? $this->domain_suffix : '';
        return "{$tls}{$this->domain_prefix}{$domain}{$suffix}";
    }

    public function getServicePorts(): array {
        $ports = [];
        /** @var DeploymentSpecificationServicePort $servicePorts */
        $servicePorts = (new DeploymentSpecificationServicePortModel())
            ->where('deployment_specification_id', $this->id)
            ->find();
        foreach ($servicePorts as $servicePort) {
            $ports[] = [
                'protocol' => $servicePort->protocol,
                'name' => $servicePort->name,
                'port' => (int)$servicePort->port,
                'targetPort' => (int)$servicePort->target_port,
            ];
        }
        return $ports;
    }

    public function getEnvironmentVariables(Deployment $deployment): array {
        if (!$deployment->database_service->exists()) {
            $deployment->database_service->find();
        }

        if (!$deployment->workspace->exists() && $deployment->workspace_id) {
            $deployment->workspace->find();
        }

        /** @var DeploymentSpecificationEnvironmentVariable $environmentVariables */
        $environmentVariables = (new DeploymentSpecificationEnvironmentVariableModel())
            ->where('deployment_specification_id', $this->id)
            ->find();

        $variables = [];
        foreach ($environmentVariables as $environmentVariable) {
            $variables[$environmentVariable->name] = EnvironmentVariable::ApplyVariablesToString(
                $environmentVariable->value,
                $deployment
            );
        }

        return $variables;
    }

    public function getServiceAnnotations(): array {
        /** @var DeploymentSpecificationServiceAnnotation $serviceAnnotations */
        $serviceAnnotations = (new DeploymentSpecificationServiceAnnotationModel())
            ->where('deployment_specification_id', $this->id)
            ->find();

        $variables = [];
        foreach ($serviceAnnotations as $serviceAnnotation) {
            $variables[$serviceAnnotation->name] = $serviceAnnotation->value;
        }

        return $variables;
    }


    // <editor-fold desc="Update methods">

    public function updatePostCommands(DeploymentSpecificationPostCommand $values): void {
        $this->deployment_specification_post_commands->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_post_commands = $values;
    }

    public function updateQuickCommands(DeploymentSpecificationQuickCommand $values): void {
        $this->deployment_specification_quick_commands->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_quick_commands = $values;
    }

    public function updateEnvironmentVariables(DeploymentSpecificationEnvironmentVariable $values): void {
        $this->deployment_specification_environment_variables->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_environment_variables = $values;
    }

    public function updateServicePorts(DeploymentSpecificationServicePort $values): void {
        $this->deployment_specification_service_ports->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_service_ports = $values;
    }

    public function updateIngresses(DeploymentSpecificationIngress $values): void {
        $this->deployment_specification_ingresses->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_ingresses = $values;
    }

    public function updateClusterRoleRules(DeploymentSpecificationClusterRoleRule $values): void {
        $this->deployment_specification_cluster_role_rules->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_cluster_role_rules = $values;
    }

    public function updateServiceAnnotations(DeploymentSpecificationServiceAnnotation $values): void {
        $this->deployment_specification_service_annotations->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_service_annotations = $values;
    }

    public function updateInitContainers(DeploymentSpecificationInitContainer $values): void {
        $this->deployment_specification_init_containers->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_init_containers = $values;
    }

    public function updatePostUpdateActions(DeploymentSpecificationPostUpdateAction $values): void {
        $this->deployment_specification_post_update_actions->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_post_update_actions = $values;
    }

    public function updateLabels(Label $values): void {
        $this->labels->find()->deleteAll();
        $this->save($values);
        $this->labels = $values;
    }

    // </editor-fold>

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);

        $item['deploymentSteps'] = array_map(fn (BaseDeploymentStep $step) => $step->toArray(), $this->getDeploymentSteps());

        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecification[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
