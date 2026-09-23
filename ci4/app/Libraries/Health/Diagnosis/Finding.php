<?php namespace App\Libraries\Health\Diagnosis;

/**
 * What one rule found: the cause, how sure it is, what it rests on, and what would fix it.
 */
readonly class Finding {

    /**
     * @param string $rule Which rule - `image_pull`, `oom_killed`, `crash_after_version_change`,
     *   `not_ready`, `rejected_by_api_server`, `migration_failed`, `health_check_path`
     * @param string $verdict A `DiagnosisVerdicts`
     * @param list<string> $evidence What the cause was read off, in the cluster's own words
     * @param array{type: string, label: string, version?: string, section?: string, migration_job_id?: int}|null $action
     *   `rollback` to a version, `section` of the deployment's page, `migration_job` to open, `deploy`,
     *   or `specification` - the deployment's specification, where the health check is set
     */
    public function __construct(
        public string $rule,
        public string $verdict,
        public string $cause,
        public array $evidence = [],
        public ?array $action = null,
    ) {
    }

    public function toArray(): array {
        return [
            'rule' => $this->rule,
            'verdict' => $this->verdict,
            'cause' => $this->cause,
            'evidence' => $this->evidence,
            'action' => $this->action,
        ];
    }

}
