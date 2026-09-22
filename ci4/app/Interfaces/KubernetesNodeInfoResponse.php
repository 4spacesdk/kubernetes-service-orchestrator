<?php namespace App\Interfaces;
/**
 * @property string $status
 * @property string $message
 * @property KubernetesNodeInfo[] $nodes
 * @property string $health_checked_at When runtime health was last worked out; old means the check has stopped.
 */
interface KubernetesNodeInfoResponse {

}
