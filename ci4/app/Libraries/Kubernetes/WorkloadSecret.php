<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use RenokiCo\PhpK8s\Kinds\K8sResource;
use RenokiCo\PhpK8s\Kinds\K8sSecret;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * The secret environment variables of one workload's pod, in a Secret of its own, which the
 * containers read with `secretKeyRef` instead of carrying the values in the pod spec.
 *
 * One per workload - the Deployment or KService, each CronJob, each Job - and owned by it,
 * so Kubernetes removes it along with the workload: at terminate, at a job's TTL, or when
 * someone deletes the workload by hand. kso never deletes one itself, except to take away
 * one a workload no longer needs.
 *
 * The init containers are in the same pod and use the same Secret. A key is the container's
 * name and the variable's, so two containers can each have their own value of one variable.
 */
class WorkloadSecret {

    /** On the pod template: the checksum of the Secret's values. */
    public const string ChecksumAnnotation = '4spaces.kso/env-secret-checksum';

    /** @var array<string, string> key => value */
    private array $data = [];

    public function __construct(public readonly string $name) {
    }

    /**
     * `<workload>-<kind>-env`: the kind in the name keeps a Deployment and a CronJob of the
     * same name from sharing one.
     */
    public static function For(string $workload, string $kind): self {
        return new self(substr("{$workload}-{$kind}", 0, 249) . '-env');
    }

    /**
     * @return string the key the variable is stored under
     */
    public function add(string $container, string $variable, string $value): string {
        $key = self::keyFor("{$container}.{$variable}");
        $this->data[$key] = $value;

        return $key;
    }

    public function isEmpty(): bool {
        return $this->data === [];
    }

    /**
     * For an annotation on the pod template, so a changed value rolls the pods - env from a
     * Secret is only read when a container starts.
     */
    public function checksum(): string {
        $data = $this->data;
        ksort($data);

        return hash('sha256', json_encode($data));
    }

    /**
     * @return array<string, string>
     */
    public function data(): array {
        return $this->data;
    }

    public function toResource(Deployment $deployment, ?KubernetesCluster $cluster = null): K8sSecret {
        $resource = new K8sSecret($cluster);
        $resource
            ->setName($this->name)
            ->setNamespace($deployment->namespace)
            ->setLabels([
                'app.kubernetes.io/managed-by' => 'kso',
                'app' => $deployment->name,
            ])
            ->setAttribute('type', 'Opaque')
            ->setData($this->data);

        return $resource;
    }

    /**
     * The values of this Secret as it is in the cluster, or null when it is not there. For
     * the preview, which hides them - see SecretPreview.
     *
     * @return array<string, string>|null
     */
    public function remoteData(Deployment $deployment, KubernetesCluster $cluster): ?array {
        $resource = $this->toResource($deployment, $cluster);

        return $resource->exists() ? $resource->get()->getData(true) : null;
    }

    /**
     * The workload and its Secret, in the order that lets the pods start: the Secret first,
     * then the workload, then the Secret again with the workload as its owner - the owner's
     * uid is only known once it exists. If that last step fails the Secret has no owner; its
     * labels say which deployment it belongs to.
     *
     * `blockOwnerDeletion`, so a workload deleted in the foreground - the migration job,
     * replaced on every run under the same name - is only gone once its Secret is, and the
     * garbage collector cannot take the new Secret for the old job's.
     *
     * @param callable(K8sResource): mixed|null $write how the workload is written, when not
     *        with an apply; a resource it returns is read for the uid
     * @param bool $removeUnused take away a Secret this workload had before and no longer
     *        needs. Not for a job: each run is a new one.
     */
    public function applyWith(K8sResource $workload, Deployment $deployment, ?callable $write = null, bool $removeUnused = true): void {
        $write ??= static fn (K8sResource $resource) => KubeHelper::Apply($resource);

        if ($this->isEmpty()) {
            $write($workload);
            if ($removeUnused) {
                $unused = $this->toResource($deployment, (new KubeAuth())->authenticate());
                if ($unused->exists()) {
                    $unused->delete();
                }
            }
            return;
        }

        $cluster = (new KubeAuth())->authenticate();
        KubeHelper::Apply($this->toResource($deployment, $cluster));
        $written = $write($workload);

        $owner = $written instanceof K8sResource ? $written : $workload->get();
        $secret = $this->toResource($deployment, $cluster);
        $secret->setAttribute('metadata.ownerReferences', [[
            'apiVersion' => $workload->getApiVersion(),
            'kind' => $workload::getKind(),
            'name' => $workload->getName(),
            'uid' => $owner->getAttribute('metadata.uid'),
            'blockOwnerDeletion' => true,
        ]]);
        KubeHelper::Apply($secret);
    }

    /**
     * A Secret's keys are letters, digits, `-`, `_` and `.`. A variable name with anything
     * else is given one that is, with a hash so two such names do not become the same key.
     */
    private static function keyFor(string $name): string {
        $key = preg_replace('/[^-._a-zA-Z0-9]/', '_', $name);

        return $key === $name ? $key : $key . '-' . substr(sha1($name), 0, 8);
    }

}
