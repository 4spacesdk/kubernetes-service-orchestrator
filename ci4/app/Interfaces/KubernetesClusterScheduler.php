<?php namespace App\Interfaces;
/**
 * @property string $last_run The last run of the job that runs every minute
 * @property string $health_checked_at
 * @property bool $behind Quiet for three minutes or more
 */
interface KubernetesClusterScheduler {

}
