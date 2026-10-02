<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * `${network.trustedProxies}` - see `TrustedProxies`. What kso cannot read from Kubernetes, said
 * by hand: the pod network, for a network plugin that keeps it out of the nodes' `podCIDRs`, and
 * the proxy-only subnet a regional or internal GKE load balancer sends from.
 */
class AddTrustedProxies extends Migration {

    public function up() {
        Table::init('systems')
            ->column('pod_network', ColumnTypes::VARCHAR_1023_NULL);

        Table::init('gateways')
            ->column('proxy_source_ranges', ColumnTypes::VARCHAR_1023_NULL);

        ApiRoute::quick('/deployments/([0-9]+)/trusted-proxies', Deployments::class, 'getTrustedProxies/$1', 'get');
    }

    public function down() {

    }

}
