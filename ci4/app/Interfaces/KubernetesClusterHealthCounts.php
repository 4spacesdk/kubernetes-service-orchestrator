<?php namespace App\Interfaces;
/**
 * How many have each health. Left out when there are none.
 *
 * @property int $healthy
 * @property int $progressing
 * @property int $degraded
 * @property int $suspended
 * @property int $missing
 * @property int $unknown
 * @property int $none No health - a Draft, or what kso cannot read the health of
 */
interface KubernetesClusterHealthCounts {

}
