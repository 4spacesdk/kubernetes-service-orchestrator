<?php namespace App\Interfaces;
/**
 * What taking a Gateway over would do.
 *
 * @property ClusterGatewayDomain[] $domains kso domains its listeners match, linked to it
 * @property ClusterGatewayDomain[] $domains_elsewhere Those that match but are on another kso gateway, left there
 * @property string[] $listeners_removed What kso's next Deploy would take away
 * @property string[] $listeners_added What kso's next Deploy would add
 */
interface ClusterGatewayPlan {

}
