<?php namespace App\Database\Migrations;

use App\Controllers\MigrationJobs;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

class AddMigrationJobsRerunRoute extends Migration {

    public function up() {
        ApiRoute::quick('/migration-jobs/([0-9]+)/rerun', MigrationJobs::class, 'rerun/$1', 'put');
    }

    public function down() {

    }

}
