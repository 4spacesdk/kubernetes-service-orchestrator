<?php namespace App\Entities;

use App\Libraries\Kubernetes\ContainerEnvironment;
use App\Libraries\Kubernetes\WorkloadSecret;
use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use RenokiCo\PhpK8s\Instances\Container;
use App\Core\Entity;
use RenokiCo\PhpK8s\Instances\Volume;

/**
 * Class InitContainer
 * @package App\Entities
 * @property string $name
 * @property int $container_image_id
 * @property ContainerImage $container_image
 * @property string $command
 * @property string $args
 * @property string $container_image_tag_policy
 * @property string $container_image_tag_value
 * @property string $container_image_pull_policy
 * @property bool $include_deployment_environment_variables
 * @property bool $include_volumes
 *
 * Many
 * @property InitContainerEnvironmentVariable $init_container_environment_variables
 * @property DeploymentSpecification $deployment_specifications
 */
class InitContainer extends Entity {

    public static function Create(): InitContainer {
        $item = new InitContainer();
        $item->save();
        return $item;
    }

    public function updateEnvironmentVariables(InitContainerEnvironmentVariable $values): void {
        $this->init_container_environment_variables->find()->deleteAll();
        $this->save($values);
        $this->init_container_environment_variables = $values;
    }

    /**
     * @param WorkloadSecret $secret the Secret of the workload whose pod this runs in: its
     *                                secret variables go there
     */
    public function toKubernetesResource(Deployment $deployment, WorkloadSecret $secret): Container {
        $spec = $deployment->findDeploymentSpecification();

        $container = new Container();
        $container
            ->setAttribute('name', $this->name)
            ->setImage(
                $this->container_image->url,
                match ($this->container_image_tag_policy) {
                    \ContainerImageTagPolicies::MatchDeployment => $deployment->version,
                    \ContainerImageTagPolicies::Static => $this->container_image_tag_value,
                    \ContainerImageTagPolicies::Default => $this->container_image->default_tag,
                }
            )
            ->setAttribute('imagePullPolicy', $this->container_image_pull_policy);
        if (strlen($this->command)) {
            $container->setAttribute('command', [
                EnvironmentVariable::ApplyVariablesToString($this->command, $deployment),
            ]);
        }
        $args = json_decode($this->args);
        if ($args != null && count($args) > 0) {
            $container->setAttribute(
                'args',
                array_map(fn($arg) => EnvironmentVariable::ApplyVariablesToString($arg, $deployment), $args)
            );
        }

        // Security Context
        if (strlen($this->container_image->security_context_run_as_user) > 0) {
            $container->setAttribute('securityContext.runAsUser', (int)$this->container_image->security_context_run_as_user);
        }
        if (strlen($this->container_image->security_context_run_as_group) > 0) {
            $container->setAttribute('securityContext.runAsGroup', (int)$this->container_image->security_context_run_as_group);
        }
        $container->setAttribute('securityContext.allowPrivilegeEscalation', (bool)$this->container_image->security_context_allow_privilege_escalation);
        $container->setAttribute('securityContext.readOnlyRootFilesystem', (bool)$this->container_image->security_context_read_only_root_filesystem);

        $environment = $this->include_deployment_environment_variables
            ? ContainerEnvironment::ofDeployment($deployment)
            : new ContainerEnvironment();
        $environment->merge(ContainerEnvironment::ofInitContainer((int) $this->id, $deployment));

        if ($this->include_volumes) {
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
            }
        }

        $environment->applyTo($container, $secret);

        return $container;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|InitContainer[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
