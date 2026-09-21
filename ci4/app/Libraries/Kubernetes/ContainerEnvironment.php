<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use App\Entities\DeploymentSpecificationEnvironmentVariable;
use App\Entities\EnvironmentVariable;
use App\Entities\InitContainerEnvironmentVariable;
use App\Models\DeploymentSpecificationEnvironmentVariableModel;
use App\Models\EnvironmentVariableModel;
use App\Models\InitContainerEnvironmentVariableModel;
use RenokiCo\PhpK8s\Instances\Instance;

/**
 * The environment variables of a container kso deploys. One place for what used to be put
 * together by hand in every step that makes a pod: the deployment, the KService, the
 * migration job, the cron jobs, the jobs RunJobHelper starts and the init containers.
 *
 * Later variables replace earlier ones with the same name. **Secret stays secret:** a
 * variable marked secret on any layer is secret, even when a later layer replaces its value
 * without the mark - an override cannot make a secret public. So is one whose value, as
 * written, takes a password from kso - see SecretPlaceholders.
 */
class ContainerEnvironment {

    /**
     * What `EnvironmentVariable::ApplyVariablesToString()` fills in with a password.
     */
    public const array SecretPlaceholders = ['${database.pass}', '${emailService.pass}'];

    /** @var array<string, string> */
    private array $values = [];

    /** @var array<string, true> */
    private array $secret = [];

    /**
     * The specification's variables, with `${…}` filled in, and then the deployment's own,
     * which replace them. The deployment's are used as they are written.
     */
    public static function ofDeployment(Deployment $deployment): self {
        $environment = new self();

        /** @var DeploymentSpecificationEnvironmentVariable $specification */
        $specification = (new DeploymentSpecificationEnvironmentVariableModel())
            ->where('deployment_specification_id', $deployment->deployment_specification_id)
            ->find();
        foreach ($specification as $variable) {
            $environment->setWritten($variable->name, (string) $variable->value, (bool) $variable->is_secret, $deployment);
        }

        /** @var EnvironmentVariable $own */
        $own = (new EnvironmentVariableModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        foreach ($own as $variable) {
            $environment->set($variable->name, (string) $variable->value, (bool) $variable->is_secret || self::takesAPassword((string) $variable->value));
        }

        return $environment;
    }

    /**
     * An init container's own variables, with `${…}` filled in.
     */
    public static function ofInitContainer(int $initContainerId, Deployment $deployment): self {
        $environment = new self();

        /** @var InitContainerEnvironmentVariable $variables */
        $variables = (new InitContainerEnvironmentVariableModel())
            ->where('init_container_id', $initContainerId)
            ->find();
        foreach ($variables as $variable) {
            $environment->setWritten($variable->name, (string) $variable->value, (bool) $variable->is_secret, $deployment);
        }

        return $environment;
    }

    public function set(string $name, string $value, bool $secret = false): self {
        $this->values[$name] = $value;
        if ($secret) {
            $this->secret[$name] = true;
        }

        return $this;
    }

    /**
     * @param array<string, string>|self $values
     */
    public function merge(array|self $values): self {
        if ($values instanceof self) {
            foreach ($values->values as $name => $value) {
                $this->set($name, $value, isset($values->secret[$name]));
            }
            return $this;
        }

        foreach ($values as $name => $value) {
            $this->set($name, (string) $value);
        }

        return $this;
    }

    public function isSecret(string $name): bool {
        return isset($this->secret[$name]);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array {
        return $this->values;
    }

    /**
     * The plain ones as values, the secret ones as references into the workload's Secret -
     * where their values are put. An Instance rather than a Container: the KService builds
     * its container as a plain one.
     */
    public function applyTo(Instance $container, WorkloadSecret $secret): void {
        foreach ($this->values as $name => $value) {
            if (!$this->isSecret($name)) {
                $container->addToAttribute('env', ['name' => $name, 'value' => $value]);
                continue;
            }

            $key = $secret->add((string) $container->getAttribute('name'), $name, $value);
            $container->addToAttribute('env', [
                'name' => $name,
                'valueFrom' => ['secretKeyRef' => ['name' => $secret->name, 'key' => $key]],
            ]);
        }
    }

    /**
     * A variable as written on a layer that fills in `${…}` - secret when marked, or when
     * what is filled in is a password.
     */
    private function setWritten(string $name, string $written, bool $marked, Deployment $deployment): void {
        $this->set(
            $name,
            EnvironmentVariable::ApplyVariablesToString($written, $deployment),
            $marked || self::takesAPassword($written)
        );
    }

    private static function takesAPassword(string $written): bool {
        foreach (self::SecretPlaceholders as $placeholder) {
            if (str_contains($written, $placeholder)) {
                return true;
            }
        }

        return false;
    }

}
