<?php namespace App\Database\Migrations;

use App\Controllers\Projects;
use App\Controllers\Users;
use App\Controllers\Workspaces;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * Projects: workspaces divided up, and users joined to the ones relevant to them - see
 * App\Entities\Project.
 *
 * One project per workspace, and none for those that exist already: they show under "no
 * project" until someone moves them. A workspace template carries the project its workspaces
 * are made in.
 */
class AddProjects extends Migration {

    private const Text511 = "VARCHAR(511) NOT NULL DEFAULT ''";

    public function up() {
        Table::init('projects')
            ->create()
            ->column('name', self::Text511)
            ->column('description', 'TEXT NULL')
            ->softDelete()
            ->timestamps();

        Table::init('projects_users')
            ->create()
            ->column('project_id', ColumnTypes::INT)->addIndex('project_id')
            ->column('user_id', ColumnTypes::INT)->addIndex('user_id');

        Table::init('workspaces')
            ->column('project_id', ColumnTypes::INT_NULL)->addIndex('project_id');
        Table::init('workspace_templates')
            ->column('project_id', ColumnTypes::INT_NULL)->addIndex('project_id');

        ApiRoute::addResourceControllerGet(Projects::class);
        ApiRoute::addResourceControllerPost(Projects::class);
        ApiRoute::addResourceControllerPatch(Projects::class);
        ApiRoute::addResourceControllerDelete(Projects::class);
        ApiRoute::quick('projects/([0-9]+)/users', Projects::class, 'updateUsers/$1', 'put');
        ApiRoute::quick('users/([0-9]+)/projects', Users::class, 'updateProjects/$1', 'put');
        ApiRoute::quick('workspaces/([0-9]+)/projectId', Workspaces::class, 'updateProjectId/$1', 'put');
    }

    public function down() {

    }

}
