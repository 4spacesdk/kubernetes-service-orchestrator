<?php namespace App\Libraries\Health\Diagnosis;

/**
 * Everything the rules look at for one deployment, as plain arrays - what the cluster answers,
 * and what only kso knows: the version it rolled out and the one before, the migration job,
 * the pull secrets the image needs, the tags the registry has.
 *
 * Gathered by `EvidenceGatherer`, built by hand in the tests, so `Diagnoser` needs neither a
 * cluster nor a database. A null is "could not be asked", which is not the same as "nothing".
 */
readonly class Evidence {

    /**
     * @param string $version The version kso has rolled out
     * @param list<array> $pods The workload's pods, not those on their way out
     * @param list<array> $events Events about the workload, its ReplicaSets and its pods
     * @param array{from: string, to: string, at: int}|null $versionChange The last time the version was changed
     * @param array{id: int, status: string, image: string, log: string}|null $lastMigration
     * @param list<string>|null $imageTags Null when the image has no registry kso can ask
     * @param string|null $imageTagsError Why the registry could not be read, when it could not
     * @param array<string, bool|null> $pullSecrets The secrets the image needs => whether the namespace has
     *   it, null when that could not be read
     * @param array|null $metrics What `DeploymentMetrics` answered, null when it was not asked
     * @param array{step: string, error: string, at: int}|null $lastDeployError The last deploy the
     *   cluster refused, when nothing has deployed that step since
     * @param array{cpu_request: ?int, cpu_limit: ?int, memory_request: ?int, memory_limit: ?int} $resources
     *   What kso gives each pod - millicores and MiB
     * @param array<string, list<string>> $previousLogs pod => the last lines of the container before this one
     */
    public function __construct(
        public string $version,
        public array $pods = [],
        public array $events = [],
        public ?array $versionChange = null,
        public ?array $lastMigration = null,
        public ?array $imageTags = null,
        public ?string $imageTagsError = null,
        public array $pullSecrets = [],
        public ?array $metrics = null,
        public ?array $lastDeployError = null,
        public array $resources = ['cpu_request' => null, 'cpu_limit' => null, 'memory_request' => null, 'memory_limit' => null],
        public array $previousLogs = [],
    ) {
    }

}
