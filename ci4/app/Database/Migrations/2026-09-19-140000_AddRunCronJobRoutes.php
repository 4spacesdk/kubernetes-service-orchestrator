<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * Running a deployment's cron job now. Signed in only - `quick()` leaves `is_public` off.
 */
class AddRunCronJobRoutes extends Migration {

    public function up() {
        ApiRoute::quick('deployments/([0-9]+)/cron-jobs/names', Deployments::class, 'getCronJobNames/$1', 'get');
        ApiRoute::quick('deployments/([0-9]+)/cron-jobs/run', Deployments::class, 'runCronJob/$1', 'post');
    }

    public function down() {

    }

}
