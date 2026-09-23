<?php namespace App\Interfaces;
/**
 * @property string $status
 * @property string $message
 * @property KubernetesNodeInfo[] $nodes
 * @property int $nodes_ready
 * @property int $nodes_total
 * @property string $kubernetes_version The first node's kubelet
 * @property string $health_checked_at When runtime health was last worked out; old means the check has stopped.
 */
interface KubernetesNodeInfoResponse {

}
