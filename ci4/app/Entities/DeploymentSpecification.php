<?php namespace App\Entities;

use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\DeploymentSteps\ClusterRoleBindingStep;
use App\Libraries\DeploymentSteps\ClusterRoleStep;
use App\Libraries\DeploymentSteps\ContourHttpProxyStep;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\CustomResourceStep;
use App\Libraries\DeploymentSteps\DatabaseStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\GatewayHttpRouteStep;
use App\Libraries\DeploymentSteps\GcpBackendPolicyStep;
use App\Libraries\DeploymentSteps\HealthCheckPolicyStep;
use App\Libraries\DeploymentSteps\IngressStep;
use App\Libraries\DeploymentSteps\IstioVirtualServiceStep;
use App\Libraries\DeploymentSteps\KServiceStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;
use App\Libraries\DeploymentSteps\RegistryPullSecretStep;
use App\Libraries\DeploymentSteps\RoleBindingStep;
use App\Libraries\DeploymentSteps\RoleStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\Libraries\DeploymentSteps\ServiceStep;
use App\Models\ContainerImageModel;
use App\Models\ContainerRegistryModel;
use App\Models\DeploymentCronJobModel;
use App\Models\DeploymentSpecificationCronJobModel;
use App\Models\DeploymentSpecificationEnvironmentVariableModel;
use App\Models\DeploymentSpecificationHttpProxyRouteModel;
use App\Models\DeploymentSpecificationInitContainerModel;
use App\Models\DeploymentSpecificationServiceAnnotationModel;
use App\Models\DeploymentSpecificationServicePortModel;
use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use App\Models\InitContainerModel;
use App\Models\K8sCronJobModel;
use App\Core\Entity;

/**
 * Class DeploymentSpecification
 * @package App\Entities
 *
 * # Mandatory settings
 * @property string $name
 * @property int $container_image_id
 * @property ContainerImage $container_image
 *
 * # Workload
 * @property string $workload_type
 *
 * # Enable features
 * @property bool $enable_database
 * @property bool $enable_cronjob
 * @property bool $enable_external_access
 * @property bool $enable_internal_access
 * @property bool $enable_rbac
 * @property bool $enable_volumes
 *
 * # Network
 * @property string $network_type
 * @property int $gateway_backend_timeout
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
 * @property DeploymentSpecificationRoleRule $deployment_specification_role_rules
 * @property DeploymentSpecificationServiceAnnotation $deployment_specification_service_annotations
 * @property DeploymentSpecificationDeploymentAnnotation $deployment_specification_deployment_annotations
 * @property DeploymentSpecificationIngressAnnotation $deployment_specification_ingress_annotations
 * @property DeploymentSpecificationQuickCommand $deployment_specification_quick_commands
 * @property DeploymentSpecificationInitContainer $deployment_specification_init_containers
 * @property DeploymentSpecificationPostUpdateAction $deployment_specification_post_update_actions
 * @property DeploymentSpecificationCronJob $deployment_specification_cron_jobs
 * @property DeploymentSpecificationHttpProxyRoute $deployment_specification_http_proxy_routes
 * @property DeploymentSpecificationVolume $deployment_specification_volumes
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
            // Level: Workspace
            NamespaceStep::class,
            ContourHttpProxyStep::class,
            GatewayHttpRouteStep::class,

            // Level: Deployment
            DatabaseStep::class,
            RegistryPullSecretStep::class,
            ServiceAccountStep::class,
            ClusterRoleStep::class,
            ClusterRoleBindingStep::class,
            RoleStep::class,
            RoleBindingStep::class,
            PersistentVolumeStep::class,
            PersistentVolumeClaimStep::class,
            CustomResourceStep::class,
            DeploymentStep::class,
            KServiceStep::class,
            ServiceStep::class,
            HealthCheckPolicyStep::class,
            GcpBackendPolicyStep::class,
            IngressStep::class,
            IstioVirtualServiceStep::class,
            MigrationJobStep::class,
            CronjobStep::class,
        ];

        $steps = [
            new NamespaceStep(),
        ];

        switch ($this->workload_type) {
            case \WorkloadTypes::Deployment:
                $steps[] = new DeploymentStep();
                break;
            case \WorkloadTypes::KNativeService:
                $steps[] = new KServiceStep();
                break;
            case \WorkloadTypes::DaemonSet:
                // Not yet supported
                break;
            case \WorkloadTypes::CustomResource:
                $steps[] = new CustomResourceStep();
                break;
        }

        if ($this->enable_database) {
            $steps[] = new DatabaseStep();
            $steps[] = new MigrationJobStep();
        }
        if ($this->enable_rbac) {
            $steps[] = new ServiceAccountStep();
            $steps[] = new ClusterRoleStep();
            $steps[] = new RoleStep();
            $steps[] = new ClusterRoleBindingStep();
            $steps[] = new RoleBindingStep();
        }
        if ($this->enable_external_access) {
            switch ($this->network_type) {
                case \NetworkTypes::NginxIngress:
                    $steps[] = new IngressStep();
                    break;
                case \NetworkTypes::Istio:
                    $steps[] = new IstioVirtualServiceStep();
                    break;
                case \NetworkTypes::Contour:
                    $steps[] = new ContourHttpProxyStep();
                    break;
                case \NetworkTypes::GatewayApi:
                    $steps[] = new GatewayHttpRouteStep();
                    break;
            }
        }
        if ($this->enable_internal_access) {
            switch ($this->workload_type) {
                case \WorkloadTypes::Deployment:
                case \WorkloadTypes::DaemonSet:
                    $steps[] = new ServiceStep();

                    // A HealthCheckPolicy targets a Service, and only a GKE Gateway acts on one. The step
                    // itself stays inert until a service port asks for a health check.
                    if ($this->enable_external_access
                        && $this->network_type == \NetworkTypes::GatewayApi
                        && System::Get()->hosting_provider == \HostingProviders::Gke) {
                        $steps[] = new HealthCheckPolicyStep();

                        // A GCPBackendPolicy also targets the Service and is likewise GKE-only. It stays
                        // inert until the specification sets a gateway_backend_timeout.
                        $steps[] = new GcpBackendPolicyStep();
                    }
                    break;
                case \WorkloadTypes::KNativeService:
                    // KNative Service is handling Service
                case \WorkloadTypes::CustomResource:
                    // Not supported
                    break;
            }
        }
        if ($this->enable_cronjob) {
            $steps[] = new CronjobStep();
        }
        if (count($this->getPullSecretRegistries($deployment)) > 0) {
            $steps[] = new RegistryPullSecretStep();
        }
        if ($this->enable_volumes && $deployment) {
            /** @var DeploymentVolume $deploymentVolumes */
            $deploymentVolumes = (new DeploymentVolumeModel())
                ->where('deployment_id', $deployment->id)
                ->find();
            if ($deploymentVolumes->exists()) {
                $steps[] = new PersistentVolumeStep();
                $steps[] = new PersistentVolumeClaimStep();
            } else {
                /** @var DeploymentSpecificationVolume $deploymentSpecificationVolumes */
                $deploymentSpecificationVolumes = (new DeploymentSpecificationVolumeModel())
                    ->where('deployment_specification_id', $this->id)
                    ->find();
                if ($deploymentSpecificationVolumes->exists()) {
                    $steps[] = new PersistentVolumeStep();
                    $steps[] = new PersistentVolumeClaimStep();
                }
            }
        }

        usort($steps, fn($a, $b) => array_search(get_class($a), $order) - array_search(get_class($b), $order));

        return $steps;
    }

    /**
     * The registries kso makes a pull secret for: those with a pull login that an
     * image of a deployment of this specification comes from - the workload, its init
     * containers, the migration job and the cron jobs, each only when turned on. Without a
     * deployment, a deployment's own cron jobs are left out.
     *
     * @return ContainerRegistry[]
     */
    public function getPullSecretRegistries(?Deployment $deployment = null): array {
        $imageIds = [$this->container_image_id];

        /** @var DeploymentSpecificationInitContainer $initContainers */
        $initContainers = (new DeploymentSpecificationInitContainerModel())
            ->includeRelated(InitContainerModel::class)
            ->where('deployment_specification_id', $this->id)
            ->find();
        foreach ($initContainers as $initContainer) {
            $imageIds[] = $initContainer->init_container->container_image_id;
        }

        if ($this->enable_database) {
            $imageIds[] = $this->database_migration_container_image_id;
        }

        if ($this->enable_cronjob) {
            /** @var K8sCronJob $cronJobs */
            $cronJobs = (new K8sCronJobModel())
                ->whereRelated(DeploymentSpecificationCronJobModel::class, 'deployment_specification_id', $this->id)
                ->find();
            foreach ($cronJobs as $cronJob) {
                $imageIds[] = $cronJob->container_image_id;
            }
            if ($deployment) {
                $cronJobs = (new K8sCronJobModel())
                    ->whereRelated(DeploymentCronJobModel::class, 'deployment_id', $deployment->id)
                    ->find();
                foreach ($cronJobs as $cronJob) {
                    $imageIds[] = $cronJob->container_image_id;
                }
            }
        }

        $imageIds = array_values(array_unique(array_filter(array_map('intval', $imageIds))));
        if (count($imageIds) == 0) {
            return [];
        }

        /** @var ContainerImage $images */
        $images = (new ContainerImageModel())
            ->whereIn('id', $imageIds)
            ->where('container_registry_id >', 0)
            ->find();
        $registryIds = array_values(array_unique(array_map(fn (ContainerImage $image) => (int) $image->container_registry_id, iterator_to_array($images))));
        if (count($registryIds) == 0) {
            return [];
        }

        /** @var ContainerRegistry $registries */
        $registries = (new ContainerRegistryModel())
            ->whereIn('id', $registryIds)
            ->orderBy('id', 'asc')
            ->find();
        return array_values(array_filter(
            iterator_to_array($registries),
            fn (ContainerRegistry $registry) => $registry->hasPullCredentials()
        ));
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
        $url = $domain->name;
        if (strlen($subdomain)) {
            $url = $subdomain . '.' . $url;
        }
        $suffix = $includeSuffix ? $this->domain_suffix : '';
        return "{$tls}{$this->domain_prefix}{$url}{$suffix}";
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

    public function getHttpProxyRoutes(): DeploymentSpecificationHttpProxyRoute {
        /** @var DeploymentSpecificationHttpProxyRoute $httpProxyRoutes */
        $httpProxyRoutes = (new DeploymentSpecificationHttpProxyRouteModel())
            ->where('deployment_specification_id', $this->id)
            ->find();
        return $httpProxyRoutes;
    }


    /**
     * The child collections that are copied row by row. Each row belongs to this
     * specification alone. The init containers, post update actions and cron jobs among
     * them are links to shared entities, so the links are copied and the entities are not.
     *
     * Not copied: `deployments`, because a copy is a new recipe, not new running
     * deployments, and the packages the original is in. The ingresses and the labels are
     * handled on their own below.
     */
    private const DuplicatedCollections = [
        'deployment_specification_post_commands',
        'deployment_specification_environment_variables',
        'deployment_specification_service_ports',
        'deployment_specification_cluster_role_rules',
        'deployment_specification_role_rules',
        'deployment_specification_service_annotations',
        'deployment_specification_deployment_annotations',
        'deployment_specification_quick_commands',
        'deployment_specification_init_containers',
        'deployment_specification_post_update_actions',
        'deployment_specification_cron_jobs',
        'deployment_specification_http_proxy_routes',
        'deployment_specification_volumes',
    ];

    /**
     * A saved copy of this specification and everything it is made of. All of it or none.
     */
    public function duplicate(): DeploymentSpecification {
        return self::inTransaction(function () {
            $copy = $this->getCopy();
            $copy->name = "{$this->name} (copy)";
            $copy->save();

            foreach (self::DuplicatedCollections as $collection) {
                foreach ($this->{$collection}->find() as $row) {
                    $rowCopy = $row->getCopy();
                    $rowCopy->deployment_specification_id = $copy->id;
                    $rowCopy->save();
                }
            }

            // An ingress has children of its own.
            foreach ($this->deployment_specification_ingresses->find() as $ingress) {
                /** @var DeploymentSpecificationIngress $ingress */
                $ingressCopy = $ingress->getCopy();
                $ingressCopy->deployment_specification_id = $copy->id;
                $ingressCopy->save();

                foreach (['deployment_specification_ingress_rule_paths', 'deployment_specification_ingress_annotations'] as $collection) {
                    foreach ($ingress->{$collection}->find() as $row) {
                        $rowCopy = $row->getCopy();
                        $rowCopy->deployment_specification_ingress_id = $ingressCopy->id;
                        $rowCopy->save();
                    }
                }
            }

            // New label rows rather than new links: `updateLabels()` deletes the rows
            // themselves, so a shared label would disappear from the original.
            $labels = new Label();
            foreach ($this->labels->find() as $label) {
                $labels->add(Label::Create($label->name, $label->value));
            }
            $copy->save($labels);

            return $copy;
        });
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

    public function updateRoleRules(DeploymentSpecificationRoleRule $values): void {
        $this->deployment_specification_role_rules->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_role_rules = $values;
    }

    public function updateServiceAnnotations(DeploymentSpecificationServiceAnnotation $values): void {
        $this->deployment_specification_service_annotations->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_service_annotations = $values;
    }

    public function updateDeploymentAnnotations(DeploymentSpecificationDeploymentAnnotation $values): void {
        $this->deployment_specification_deployment_annotations->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_deployment_annotations = $values;
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

    public function updateCronJobs(DeploymentSpecificationCronJob $values): void {
        $this->deployment_specification_cron_jobs->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_cron_jobs = $values;
    }

    public function updateHttpProxyRoutes(DeploymentSpecificationHttpProxyRoute $values): void {
        $this->deployment_specification_http_proxy_routes->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_http_proxy_routes = $values;
    }

    /**
     * Why the specification cannot be given this many volumes, or null when it can. See
     * `Deployment::volumeCountProblem()`: every deployment on it mounts them alongside its
     * own, and a deployment mounts at most one.
     */
    public function volumeCountProblem(int $volumes): ?string {
        if ($volumes > 1) {
            return 'A specification can have one volume';
        }
        if ($volumes === 1) {
            $names = array_column(
                db_connect()->table('deployments')
                    ->distinct()
                    ->select('deployments.name')
                    ->join('deployment_volumes', 'deployment_volumes.deployment_id = deployments.id AND deployment_volumes.deletion_id IS NULL')
                    ->where('deployments.deployment_specification_id', $this->id)
                    ->where('deployments.deletion_id', null)
                    ->orderBy('deployments.name')
                    ->get()->getResultArray(),
                'name'
            );
            if ($names) {
                return 'These deployments have a volume of their own: ' . implode(', ', $names);
            }
        }
        return null;
    }

    public function updateVolumes(DeploymentSpecificationVolume $values): void {
        $this->deployment_specification_volumes->find()->deleteAll();
        $this->save($values);
        $this->deployment_specification_volumes = $values;
    }

    // </editor-fold>

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array {
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
