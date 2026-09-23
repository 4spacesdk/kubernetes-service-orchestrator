<?php namespace App\Interfaces;
/**
 * A Gateway in the cluster, held up against kso's - see `ClusterGateways`.
 *
 * @property string $namespace
 * @property string $name
 * @property string $gateway_class_name
 * @property GatewayAddress[] $addresses
 * @property ClusterGatewayListener[] $listeners
 * @property string $status known, unknown, or orphan - kso's mark on it and no row in kso
 * @property int $gateway_id kso's, when it is known
 * @property string[] $differences For a known one, what differs between the cluster and kso
 * @property ClusterGatewayPlan $plan For one kso does not have, what taking it over would do
 */
interface ClusterGateway {

}
