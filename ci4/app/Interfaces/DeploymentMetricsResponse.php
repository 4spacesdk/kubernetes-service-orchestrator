<?php namespace App\Interfaces;
/**
 * What a deployment's pods are using right now. Cpu in millicores and memory in bytes - the units
 * the numbers are compared in, so the web app does no arithmetic of its own.
 *
 * @property bool $available Whether the cluster could be asked at all - metrics-server is not part of Kubernetes
 * @property string $reason Why not, when it could not
 * @property string $window The period metrics-server measured over
 * @property int $cpu_millicores Across the pods
 * @property int $memory_bytes Across the pods
 * @property int $cpu_request What one pod asks for
 * @property int $cpu_limit What one pod may use
 * @property int $memory_request_bytes What one pod asks for
 * @property int $memory_limit_bytes What one pod may use
 * @property DeploymentPodMetrics[] $pods
 */
interface DeploymentMetricsResponse {

}
