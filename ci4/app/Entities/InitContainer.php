<?php namespace App\Entities;

use App\Models\DeploymentSpecificationVolumeModel;
use App\Models\DeploymentVolumeModel;
use App\Models\EnvironmentVariableModel;
use App\Models\InitContainerEnvironmentVariableModel;
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

    public function toKubernetesResource(Deployment $deployment): Container {
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

        $envVars = [];

        if ($this->include_deployment_environment_variables) {
            $specEnvVars = $spec->getEnvironmentVariables($deployment);
            foreach ($specEnvVars as $key => $value) {
                $envVars[$key] = $value;
            }
            /** @var EnvironmentVariable $deploymentEnvironmentVariables */
            $deploymentEnvironmentVariables = (new EnvironmentVariableModel())
                ->where('deployment_id', $deployment->id)
                ->find();
            foreach ($deploymentEnvironmentVariables as $deploymentEnvironmentVariable) {
                $envVars[$deploymentEnvironmentVariable->name] = $deploymentEnvironmentVariable->value;
            }
        }

        $initContainerEnvVars = $this->getEnvironmentVariables($deployment);
        foreach ($initContainerEnvVars as $key => $value) {
            $envVars[$key] = $value;
        }

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

        $container->addEnvs($envVars);

        return $container;
    }

    public function getEnvironmentVariables(Deployment $deployment): array {
        if (!$deployment->database_service->exists()) {
            $deployment->database_service->find();
        }
        if (!$deployment->workspace->exists() && $deployment->workspace_id) {
            $deployment->workspace->find();
        }

        /** @var InitContainerEnvironmentVariable $environmentVariables */
        $environmentVariables = (new InitContainerEnvironmentVariableModel())
            ->where('init_container_id', $this->id)
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

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|InitContainer[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
