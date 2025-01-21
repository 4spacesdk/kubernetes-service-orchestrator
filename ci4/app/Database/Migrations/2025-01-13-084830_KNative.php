<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use App\Controllers\DeploymentSpecifications;
use App\Controllers\Domains;
use App\Controllers\Kubernetes;
use App\Controllers\Systems;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class KNative extends Migration {

    public function up() {
        Table::init('deployments')
            ->dropColumn('enable_podio_notification');
        Table::init('deployment_package_deployment_specifications')
            ->dropColumn('default_enable_podio_notification');

        Table::init('deployments')
            ->column('knative_concurrency_limit_soft', ColumnTypes::INT)
            ->column('knative_concurrency_limit_hard', ColumnTypes::INT)
            ->dropColumn('domain_id')
            ->dropColumn('subdomain')
            ->dropColumn('aliases');

        Table::init('deployment_specifications')
            ->column('workload_type', ColumnTypes::VARCHAR_27)
            ->column('network_type', ColumnTypes::VARCHAR_27)
            ->column('enable_external_access', ColumnTypes::BOOL_0)
            ->column('enable_internal_access', ColumnTypes::BOOL_0)
            ->dropColumn('type');
        Database::connect()
            ->table('deployment_specifications')
            ->where('workload_type', '')
            ->set('workload_type', \WorkloadTypes::Deployment)
            ->update();
        Database::connect()
            ->query('update deployment_specifications set enable_external_access = enable_ingress where enable_external_access = ""');
        Database::connect()
            ->query('update deployment_specifications set enable_internal_access = true where enable_internal_access = ""');
        Table::init('deployment_specifications')
            ->dropColumn('enable_ingress');
        Database::connect()
            ->table('deployment_specifications')
            ->where('network_type', '')
            ->set('network_type', \NetworkTypes::NginxIngress)
            ->update();

        Table::init('deployment_package_deployment_specifications')
            ->column('default_knative_concurrency_limit_soft', ColumnTypes::INT)
            ->column('default_knative_concurrency_limit_hard', ColumnTypes::INT);

        Table::init('deployment_specification_http_proxy_routes')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('path', ColumnTypes::VARCHAR_27)
            ->column('port', ColumnTypes::INT)
            ->column('protocol', ColumnTypes::VARCHAR_27);

        ApiRoute::quick('/deployment-specifications/([0-9]+)/http-proxy-routes', DeploymentSpecifications::class, 'updateDeploymentHttpProxyRoutes/$1', 'put');

        ApiRoute::quick('/deployments/([0-9]+)/workspace', Deployments::class, 'updateWorkspace/$1', 'put');


        Table::init('domains')
            ->column('enable_istio_gateway', ColumnTypes::BOOL_0)
            ->column('enable_contour', ColumnTypes::BOOL_0)
            ->column('contour_ingress_class_name', ColumnTypes::VARCHAR_63);

        ApiRoute::quick('/domains/([0-9]+)/istio-gateway/apply', Domains::class, 'applyIstioGateway/$1', 'put');
        ApiRoute::quick('/domains/([0-9]+)/istio-gateway/terminate', Domains::class, 'terminateIstioGateway/$1', 'put');


        ApiRoute::quick('/deployments/([0-9]+)/resourceManagement', Deployments::class, 'updateResourceManagement/$1', 'put');


        Table::init('systems')
            ->column('is_network_nginx_ingress_supported', ColumnTypes::BOOL_0)
            ->column('is_network_istio_supported', ColumnTypes::BOOL_0)
            ->column('is_network_contour_supported', ColumnTypes::BOOL_0);
        ApiRoute::addResourceControllerPatch(Systems::class);


        ApiRoute::quick('kubernetes/namespaces/(.*)/pods/(.*)/containers/(.*)/exec', Kubernetes::class, 'exec/$1/$2/$3', 'put');
        ApiRoute::quick('kubernetes/namespaces/(.*)/pods/(.*)/containers/(.*)/logs', Kubernetes::class, 'getLogs/$1/$2/$3', 'get');
        ApiRoute::quick('kubernetes/namespaces/(.*)/pods/(.*)/containers/(.*)/logs/watch', Kubernetes::class, 'watchLogs/$1/$2/$3', 'put');

        Table::init('deployments')
            ->dropColumn('custom_resource');
    }

    public function down() {

    }

}
