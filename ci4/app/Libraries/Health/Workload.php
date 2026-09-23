<?php namespace App\Libraries\Health;

use App\Entities\Deployment;

/**
 * The part of a deployment its health is worked out from, lifted off the entity so that
 * `HealthEvaluator` needs neither the database nor the ORM - it is tested in the unit suite.
 */
readonly class Workload {

    /**
     * @param string|null $suspendedBecause Why kso is not to look at the cluster for it -
     *   a paused workspace or a deployment switched off. Null when it should be looked at.
     * @param array{status: string, image: string}|null $lastMigration
     * @param array|null $customResource The custom resource as the cluster has it, for a workload
     *   that is one: it is the only thing that can say how it is doing. Null when there is none to
     *   read - it is not in the cluster, or it could not be fetched.
     */
    public function __construct(
        public string $workloadType,
        public string $namespace,
        public string $name,
        public string $version,
        public ?string $suspendedBecause = null,
        public ?array $lastMigration = null,
        public ?array $customResource = null,
    ) {
    }

    public static function Of(Deployment $deployment, string $workloadType, ?array $customResource = null): Workload {
        $suspendedBecause = null;
        if ($deployment->status === \DeploymentStatusTypes::Inactive) {
            $suspendedBecause = 'Switched off';
        } else if ($deployment->isInAPausedOrInactiveWorkspace()) {
            $suspendedBecause = 'The workspace is paused or switched off';
        }

        $lastMigration = null;
        $job = $deployment->findLastMigrationJob();
        if ($job) {
            $lastMigration = [
                'status' => (string) $job->status,
                'image' => (string) $job->image,
            ];
        }

        return new Workload(
            $workloadType,
            (string) $deployment->namespace,
            (string) $deployment->name,
            (string) $deployment->version,
            $suspendedBecause,
            $lastMigration,
            $customResource,
        );
    }

}
