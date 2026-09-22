<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * A deployment's logs, across its pods: the last lines, and a follow that reads every pod in the
 * one request - see `DeploymentLogs`.
 *
 * The per-pod routes stay. They are what the pods dialog and the migration job log use, and one
 * pod at a time is still the right view when that is the question.
 */
class AddDeploymentLogRoutes extends Migration {

    public function up() {
        ApiRoute::quick('deployments/([0-9]+)/logs', Deployments::class, 'getLogs/$1', 'get');
        ApiRoute::quick('deployments/([0-9]+)/logs/watch', Deployments::class, 'watchLogs/$1', 'put');
    }

    public function down() {

    }

}
