<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentPackages;
use App\Controllers\DeploymentSpecifications;
use App\Controllers\Kubernetes;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddDeploymentSpecifications extends Migration {

    public function up() {
        Table::init('deployment_specifications')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('container_image_id', ColumnTypes::INT)->addIndex('container_image_id')
            ->column('git_repo', ColumnTypes::VARCHAR_1023)

            ->column('enable_database', ColumnTypes::BOOL_0)->addIndex('enable_database')
            ->column('enable_cronjob', ColumnTypes::BOOL_0)->addIndex('enable_cronjob')
            ->column('enable_ingress', ColumnTypes::BOOL_0)->addIndex('enable_ingress')
            ->column('enable_rbac', ColumnTypes::BOOL_0)->addIndex('enable_rbac')

            ->column('domain_tls', ColumnTypes::VARCHAR_27)
            ->column('domain_prefix', ColumnTypes::VARCHAR_127)
            ->column('domain_suffix', ColumnTypes::VARCHAR_127)
            ->column('domain_aliases', ColumnTypes::VARCHAR_511)

            ->column('database_migration_command', ColumnTypes::VARCHAR_1023)

            ->column('cronjob_url', ColumnTypes::VARCHAR_1023)

            ->timestamps();

        Table::init('deployment_specification_post_commands')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('command', ColumnTypes::VARCHAR_1023);

        Table::init('deployment_specification_environment_variables')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('value', ColumnTypes::TEXT);

        Table::init('deployment_specification_service_ports')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('protocol', ColumnTypes::VARCHAR_127)
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('port', ColumnTypes::INT)
            ->column('target_port', ColumnTypes::INT);

        Table::init('deployment_specification_ingress_rule_paths')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('path', ColumnTypes::VARCHAR_127)
            ->column('path_type', ColumnTypes::VARCHAR_127)
            ->column('backend_service_port_name', ColumnTypes::VARCHAR_127);

        Table::init('deployment_specification_cluster_role_rules')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('workspace_type', ColumnTypes::VARCHAR_127)
            ->column('api_group', ColumnTypes::VARCHAR_511)
            ->column('resource', ColumnTypes::VARCHAR_511)
            ->column('verbs', ColumnTypes::VARCHAR_1023);

        ApiRoute::addResourceController(DeploymentSpecifications::class);
        ApiRoute::quick('/kubernetes/rbac/show', Kubernetes::class, 'rbacShow', 'get');

        ApiRoute::quick('/deployment-specifications/([0-9]+)/post-commands', DeploymentSpecifications::class, 'updatePostCommands/$1', 'put');
        ApiRoute::quick('/deployment-specifications/([0-9]+)/environment-variables', DeploymentSpecifications::class, 'updateEnvironmentVariables/$1', 'put');
        ApiRoute::quick('/deployment-specifications/([0-9]+)/service-ports', DeploymentSpecifications::class, 'updateServicePorts/$1', 'put');
        ApiRoute::quick('/deployment-specifications/([0-9]+)/ingress-rule-paths', DeploymentSpecifications::class, 'updateIngressRulePaths/$1', 'put');
        ApiRoute::quick('/deployment-specifications/([0-9]+)/cluster-role-rules', DeploymentSpecifications::class, 'updateClusterRoleRules/$1', 'put');

        Table::init('deployments')
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('enable_podio_notification', ColumnTypes::BOOL_0)
            ->dropColumn('spec');

        ApiRoute::quick('/deployment-specifications/([0-9]+)/tags', DeploymentSpecifications::class, 'getTags/$1', 'get');

        Table::init('deployment_packages')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('default_email_service_id', ColumnTypes::INT)
            ->column('default_database_service_id', ColumnTypes::INT)
            ->column('default_domain_id', ColumnTypes::INT);

        Table::init('deployment_package_deployment_specifications')
            ->create()
            ->column('deployment_package_id', ColumnTypes::INT)->addIndex('deployment_package_id')
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('default_enable_podio_notification', ColumnTypes::BOOL_0)
            ->column('default_version', ColumnTypes::VARCHAR_127)
            ->column('default_keel_policy', ColumnTypes::VARCHAR_127)
            ->column('default_keel_auto_update', ColumnTypes::BOOL_0)
            ->column('default_environment', ColumnTypes::VARCHAR_127)
            ->column('default_cpu_request', ColumnTypes::INT)
            ->column('default_cpu_limit', ColumnTypes::INT)
            ->column('default_memory_request', ColumnTypes::INT)
            ->column('default_memory_limit', ColumnTypes::INT)
            ->column('default_replicas', ColumnTypes::INT)
            ->timestamps();

        Table::init('workspaces')
            ->column('deployment_package_id', ColumnTypes::INT)->addIndex('deployment_package_id')
            ->dropColumn('type');

        Table::init('systems')
            ->dropColumn('default_email_service_id')
            ->dropColumn('default_database_service_id')
            ->dropColumn('default_domain_id');

        ApiRoute::addResourceController(DeploymentPackages::class);
        ApiRoute::quick('/deployment-packages/([0-9]+)/deployment-specifications', DeploymentPackages::class, 'updateDeploymentSpecifications/$1', 'put');


    }

    public function down() {

    }

}
