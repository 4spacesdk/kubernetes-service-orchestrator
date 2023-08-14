<?php namespace App\Entities;

use App\Libraries\DeploymentSpecs\PushServiceSpec;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\DeploymentSteps\ClusterRoleBindingStep;
use App\Libraries\DeploymentSteps\ClusterRoleStep;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\DatabaseStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\IngressStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\OauthStep;
use App\Libraries\DeploymentSteps\RedirectsStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\Libraries\DeploymentSteps\ServiceStep;
use App\Models\DeploymentSpecificationEnvironmentVariableModel;
use App\Models\DeploymentSpecificationIngressRulePathModel;
use App\Models\DeploymentSpecificationResourceManagementProfileModel;
use App\Models\DeploymentSpecificationServicePortModel;
use RestExtension\Core\Entity;

/**
 * Class DeploymentSpecification
 * @package App\Entities
 *
 * # Mandatory settings
 * @property string $name
 * @property int $container_image_id
 * @property ContainerImage $container_image
 * @property string $git_repo
 *
 * # Enable features
 * @property bool $enable_database
 * @property bool $enable_cronjob
 * @property bool $enable_ingress
 * @property bool $enable_rbac
 *
 * # Domain settings
 * @property string $domain_tls
 * @property string $domain_prefix
 * @property string $domain_suffix
 * @property string $domain_aliases
 *
 * # Database Migration settings
 * @property string $database_migration_command
 *
 * # Cron Job settings
 * @property string $cronjob_url
 *
 * Many
 * @property Deployment $deployments
 * @property DeploymentSpecificationPostCommand $deployment_specification_post_commands
 * @property DeploymentSpecificationEnvironmentVariable $deployment_specification_environment_variables
 * @property DeploymentSpecificationServicePort $deployment_specification_service_ports
 * @property DeploymentSpecificationIngressRulePath $deployment_specification_ingress_rule_paths
 * @property DeploymentSpecificationClusterRoleRule $deployment_specification_cluster_role_rules
 *
 * @property DeploymentStep $deploymentSteps
 */
class DeploymentSpecification extends Entity {

    public function getDeploymentSteps(?Deployment $deployment = null): array {
        $order = [
            OauthStep::class,
            DatabaseStep::class,
            NamespaceStep::class,
            ClusterRoleStep::class,
            ServiceAccountStep::class,
            ClusterRoleBindingStep::class,
            DeploymentStep::class,
            ServiceStep::class,
            IngressStep::class,
            RedirectsStep::class,
            MigrationJobStep::class,
            CronjobStep::class,
        ];

        $steps = [
            new NamespaceStep(),
            new DeploymentStep(),
            new ServiceStep(),
            new OauthStep(),
        ];

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
            if (strlen($deployment?->aliases ?? $this->domain_aliases)) {
                $steps[] = new RedirectsStep();
            }
        }
        if ($this->enable_cronjob) {
            $steps[] = new CronjobStep();
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

    public function getUrl(string $subdomain, Domain $domain, bool $includeTls = false): string {
        $tls = $includeTls ? "{$this->domain_tls}://" : '';
        $domain = $domain->name;
        if (strlen($subdomain)) {
            $domain = $subdomain . '.' . $domain;
        }
        return "{$tls}{$this->domain_prefix}{$domain}{$this->domain_suffix}";
    }

    public function getIngressRules(Deployment $deployment): array {
        if (!$deployment->domain->exists()) {
            $deployment->domain->find();
        }

        $paths = [];
        /** @var DeploymentSpecificationIngressRulePath $ingressRulePaths */
        $ingressRulePaths = (new DeploymentSpecificationIngressRulePathModel())
            ->where('deployment_specification_id', $this->id)
            ->find();
        foreach ($ingressRulePaths as $ingressRulePath) {
            $paths[] = [
                'path' => $ingressRulePath->path,
                'pathType' => $ingressRulePath->path_type,
                'backend' => [
                    'service' => [
                        'name' => $deployment->name,
                        'port' => [
                            'name' => $ingressRulePath->backend_service_port_name,
                        ],
                    ],
                ],
            ];
        }

        return [
            [
                'host' => $deployment->getUrl(),
                'http' => [
                    'paths' => $paths,
                ],
            ]
        ];
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
                'port' => $servicePort->port,
                'targetPort' => $servicePort->target_port,
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
            $variables[$environmentVariable->name] = $environmentVariable->generateValue($deployment->workspace, $deployment);
        }

        return $variables;
    }


    // <editor-fold desc="Update methods">

    public function updatePostCommands(DeploymentSpecificationPostCommand $values): void {
        $this->deployment_specification_post_commands->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_post_commands = $values;
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

    public function updateIngressRulePaths(DeploymentSpecificationIngressRulePath $values): void {
        $this->deployment_specification_ingress_rule_paths->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_ingress_rule_paths = $values;
    }

    public function updateClusterRoleRules(DeploymentSpecificationClusterRoleRule $values): void {
        $this->deployment_specification_cluster_role_rules->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_cluster_role_rules = $values;
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
    public function getIterator() {
        return parent::getIterator();
    }

}
