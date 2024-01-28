<?php namespace App\Entities;

use App\Models\EnvironmentVariableModel;
use App\Models\InitContainerEnvironmentVariableModel;
use RenokiCo\PhpK8s\Instances\Container;
use RestExtension\Core\Entity;

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
                }
            )
            ->setAttribute('imagePullPolicy', $this->container_image_pull_policy);
        if (strlen($this->command)) {
            $container->setAttribute('command', [
                $this->applyVariablesToString($this->command, $deployment)
            ]);
        }
        $args = json_decode($this->args);
        if ($args != null && count($args) > 0) {
            $container->setAttribute(
                'args',
                array_map(fn($arg) => $this->applyVariablesToString($arg, $deployment), $args)
            );
        }

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

        $container->addEnvs($envVars);

        return $container;
    }

    public function applyVariablesToString(string $value, Deployment $deployment): string {
        if (!$deployment->database_service->exists() && $deployment->database_service_id) {
            $deployment->database_service->find();
        }
        if ($deployment->workspace_id) {
            if (!$deployment->workspace->exists()) {
                $deployment->workspace->find();
            }
            if (!$deployment->workspace->email_service->exists() && $deployment->workspace->email_service_id) {
                $deployment->workspace->email_service->find();
            }
        }

        $modifiers = [
            fn(string $value) => str_replace('${namespace}', $deployment->namespace, $value),

            fn(string $value) => str_replace('${database.host}', $deployment->database_service->host, $value),
            fn(string $value) => str_replace('${database.name}', $deployment->database_name, $value),
            fn(string $value) => str_replace('${database.user}', $deployment->database_service->getDatabaseUser($deployment->database_user), $value),
            fn(string $value) => str_replace('${database.pass}', $deployment->database_pass, $value),

            fn(string $value) => str_replace('${emailService.host}', $deployment->workspace->email_service->host, $value),
            fn(string $value) => str_replace('${emailService.port}', $deployment->workspace->email_service->port, $value),
            fn(string $value) => str_replace('${emailService.user}', $deployment->workspace->email_service->user, $value),
            fn(string $value) => str_replace('${emailService.pass}', $deployment->workspace->email_service->pass, $value),
            fn(string $value) => str_replace('${emailService.sender}', $deployment->workspace->email_service->from, $value),

            fn(string $value) => str_replace('${workspace.id}', $deployment->workspace->id, $value),
            fn(string $value) => str_replace('${workspace.name}', $deployment->workspace->namespace, $value),

            fn(string $value) => str_replace('${migration.job.name}', $deployment->name, $value),
        ];

        foreach ($modifiers as $fn) {
            $value = $fn($value);
        }

        return $value;
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
            $variables[$environmentVariable->name] = $environmentVariable->generateValue($deployment->workspace, $deployment);
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
