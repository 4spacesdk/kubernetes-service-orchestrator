<?php namespace App\Interfaces;
/**
 * What one rule found.
 *
 * @property string $rule image_pull, oom_killed, crash_after_version_change, not_ready, rejected_by_api_server or migration_failed
 * @property string $verdict certain, possible or cannot_tell - see DiagnosisVerdicts
 * @property string $cause
 * @property string[] $evidence What the cause was read off, in the cluster's own words
 * @property DeploymentDiagnosisAction $action What would fix it, when there is something to press
 */
interface DeploymentDiagnosisFinding {

}
