<?php namespace App\Interfaces;
/**
 * @property string $type rollback, section, migration_job, deploy or specification
 * @property string $label
 * @property string $version For rollback: the version to go back to
 * @property string $section For section: the key of the deployment page's section
 * @property int $migration_job_id For migration_job
 */
interface DeploymentDiagnosisAction {

}
