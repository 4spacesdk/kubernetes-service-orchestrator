<?php namespace App\Database\Migrations;

use App\Controllers\DeploymentSpecifications;
use App\Controllers\PodioIntegrations;
use App\Controllers\PostUpdateActions;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddPostUpdateActions extends Migration {

    public function up() {
        Table::init('podio_integrations')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('client_id', ColumnTypes::VARCHAR_511)
            ->column('client_secret', ColumnTypes::VARCHAR_511)
            ->column('app_id', ColumnTypes::VARCHAR_63)
            ->column('app_token', ColumnTypes::VARCHAR_63)
            ->softDelete()
            ->timestamps();

        Table::init('podio_field_references')
            ->create()
            ->column('podio_integration_id', ColumnTypes::INT)->addIndex('podio_integration_id')
            ->column('field_id', ColumnTypes::VARCHAR_63);

        ApiRoute::addResourceControllerGet(PodioIntegrations::class);
        ApiRoute::addResourceControllerPost(PodioIntegrations::class);
        ApiRoute::addResourceControllerPatch(PodioIntegrations::class);
        ApiRoute::addResourceControllerDelete(PodioIntegrations::class);

        Table::init('post_update_actions')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('type', ColumnTypes::VARCHAR_511)

            // Type: Podio, Add Comment
            ->column('podio_add_comment_integration_id', ColumnTypes::INT)
            ->column('podio_add_comment_value', ColumnTypes::TEXT)

            // Type: Podio, Field Update
            ->column('podio_field_update_field_reference_id', ColumnTypes::INT)
            ->column('podio_field_update_value', ColumnTypes::TEXT)
        ;

        Table::init('post_update_action_conditions')
            ->create()
            ->column('post_update_action_id', ColumnTypes::INT)->addIndex('post_update_action_id')
            ->column('type', ColumnTypes::VARCHAR_511)
            ->column('podio_field_reference_id', ColumnTypes::INT)->addIndex('podio_field_reference_id')
            ->column('value', ColumnTypes::VARCHAR_511)
        ;

        Table::init('deployment_specification_post_update_actions')
            ->create()
            ->column('deployment_specification_id', ColumnTypes::INT)->addIndex('deployment_specification_id')
            ->column('post_update_action_id', ColumnTypes::INT)->addIndex('post_update_action_id')
            ->column('position', ColumnTypes::INT)
        ;

        ApiRoute::addResourceControllerGet(PostUpdateActions::class);
        ApiRoute::addResourceControllerPost(PostUpdateActions::class);
        ApiRoute::addResourceControllerPatch(PostUpdateActions::class);
        ApiRoute::addResourceControllerDelete(PostUpdateActions::class);
        ApiRoute::quick('/post-update-actions/([0-9]+)/conditions', PostUpdateActions::class, 'updateConditions/$1', 'put');
        ApiRoute::quick('/deployment-specifications/([0-9]+)/post-update-actions', DeploymentSpecifications::class, 'updatePostUpdateActions/$1', 'put');
    }

    public function down() {

    }

}
