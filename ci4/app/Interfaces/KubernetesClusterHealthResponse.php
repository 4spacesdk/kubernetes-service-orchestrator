<?php namespace App\Interfaces;
/**
 * The window behind the status bar's dot - see `ClusterHealth`.
 *
 * @property KubernetesClusterNode[] $nodes
 * @property bool $metrics_available Whether metrics-server answered - it is not part of Kubernetes
 * @property string $metrics_reason Why not, when it did not
 * @property KubernetesClusterHealthCounts $deployments
 * @property KubernetesClusterHealthCounts $workspaces
 * @property KubernetesClusterScheduler $scheduler
 * @property KubernetesClusterNamespace[] $namespaces
 */
interface KubernetesClusterHealthResponse {

}
