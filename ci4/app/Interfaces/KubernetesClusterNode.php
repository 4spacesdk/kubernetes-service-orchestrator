<?php namespace App\Interfaces;
/**
 * One node, in millicores and bytes.
 *
 * @property string $name
 * @property bool $ready
 * @property string $ready_reason What the kubelet said, when not ready
 * @property bool $unschedulable Cordoned
 * @property string[] $roles
 * @property string $kubelet_version
 * @property int $age_seconds
 * @property string[] $pressures MemoryPressure, DiskPressure or PIDPressure, those that are true
 * @property int $pods Running on it
 * @property int $pods_allocatable
 * @property int $cpu_capacity
 * @property int $cpu_allocatable What pods may be scheduled onto
 * @property int $cpu_requested What the pods on it asked for
 * @property int $cpu_usage Now; empty without metrics-server
 * @property int $memory_capacity
 * @property int $memory_allocatable
 * @property int $memory_requested
 * @property int $memory_usage
 */
interface KubernetesClusterNode {

}
