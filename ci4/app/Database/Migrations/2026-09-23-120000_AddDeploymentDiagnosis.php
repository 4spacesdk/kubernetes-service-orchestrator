<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * Why a deployment is doing badly - see `Libraries/Health/Diagnosis`.
 *
 * The last deploy the cluster refused is kept on the deployment until that step deploys again,
 * so the diagnosis can say what the api server answered.
 */
class AddDeploymentDiagnosis extends Migration {

    public function up() {
        Table::init('deployments')
            ->column('last_deploy_error', 'TEXT')
            ->column('last_deploy_error_step', 'VARCHAR(127)')
            ->column('last_deploy_error_at', ColumnTypes::DATETIME);

        ApiRoute::quick('deployments/([0-9]+)/diagnosis', Deployments::class, 'getDiagnosis/$1', 'get');
    }

    public function down() {

    }

}
