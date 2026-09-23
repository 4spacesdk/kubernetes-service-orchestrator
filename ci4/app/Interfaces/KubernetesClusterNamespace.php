<?php namespace App\Interfaces;
/**
 * @property string $name
 * @property string $owner kso - this kso's workspace or deployment is in it - theirs - another kso's - kubernetes, or other
 * @property int $pods
 * @property int $age_seconds
 * @property int $workspace_id The workspace in it, for one of kso's
 * @property string $workspace
 */
interface KubernetesClusterNamespace {

}
