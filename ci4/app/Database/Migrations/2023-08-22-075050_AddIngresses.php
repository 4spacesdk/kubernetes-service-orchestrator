<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentSpecificationIngress;
use App\Entities\DeploymentSpecificationIngressRulePath;
use App\Models\DeploymentSpecificationIngressRulePathModel;
use App\Models\DeploymentSpecificationModel;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddIngresses extends Migration {

    public function up() {
        Table::init('deployment_specification_ingresses')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('ingress_class', ColumnTypes::VARCHAR_63)
            ->column('proxy_body_size', ColumnTypes::INT)
            ->column('proxy_connect_timeout', ColumnTypes::INT)
            ->column('proxy_read_timeout', ColumnTypes::INT)
            ->column('proxy_send_timeout', ColumnTypes::INT)
            ->column('ssl_redirect', ColumnTypes::BOOL_0)
            ->column('enable_tls', ColumnTypes::BOOL_0);

        Table::init('deployment_specification_ingress_rule_paths')
            ->column('deployment_specification_ingress_id', ColumnTypes::INT)->addIndex('deployment_specification_ingress_id');

        // Add ingresses
        /** @var DeploymentSpecificationIngressRulePath $rulePaths */
        $rulePaths = (new DeploymentSpecificationIngressRulePathModel())
            ->find();
        $deploymentSpecificationId2RulePaths = [];
        foreach ($rulePaths as $rulePath) {
            if ($rulePath->deployment_specification_id) {
                if (!isset($deploymentSpecificationId2RulePaths[$rulePath->deployment_specification_id])) {
                    $deploymentSpecificationId2RulePaths[$rulePath->deployment_specification_id] = [];
                }
                $deploymentSpecificationId2RulePaths[$rulePath->deployment_specification_id][] = $rulePath;
            }
        }

        foreach ($deploymentSpecificationId2RulePaths as $deploymentSpecificationId => $rulePaths) {
            /** @var DeploymentSpecification $spec */
            $spec = (new DeploymentSpecificationModel())
                ->where('id', $deploymentSpecificationId)
                ->find();

            $ingress = DeploymentSpecificationIngress::Create(
                'nginx',
                50,
                600,
                600,
                600,
                true,
                true,
                array_map(
                    function(DeploymentSpecificationIngressRulePath $path) {
                        $class = new \stdClass();
                        $class->path = $path->path;
                        $class->pathType = $path->path_type;
                        $class->backendServicePortName = $path->backend_service_port_name;
                        return $class;
                    },
                    $rulePaths
                ),
            );

            $values = new DeploymentSpecificationIngress();
            $values->all = [$ingress];
            $spec->updateIngresses($values);
        }

        Table::init('deployment_specification_ingress_rule_paths')
            ->dropColumn('deployment_specification_id');

        ApiRoute::quick('/deployment-specifications/([0-9]+)/ingresses', DeploymentSpecifications::class, 'updateIngresses/$1', 'put');
    }

    public function down() {

    }

}
