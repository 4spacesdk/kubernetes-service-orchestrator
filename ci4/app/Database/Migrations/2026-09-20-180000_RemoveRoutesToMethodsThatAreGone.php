<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Five routes naming a controller method that is not there. A call on any of them is a
 * `PageNotFoundException` - there is nothing behind them to reach.
 *
 * `requestSupportLogin` was written by the initial migration in 2023 and the method went
 * some time after; the other four are the same story with no date on it. They are data,
 * written once and never held against the code again, which is the whole of why they
 * outlived the methods they name.
 *
 * Nothing called them - they cannot be called - but they were generated into the API client
 * and published in the OpenAPI document, where they read as endpoints a caller may use. The
 * sweep in `ApiRouteTableTest` is what keeps the next one from lasting three years.
 */
class RemoveRoutesToMethodsThatAreGone extends Migration {

    public function up() {
        Database::connect()
            ->table('api_routes')
            ->whereIn('to', [
                'App\Controllers\DeploymentSpecifications::updateIngressRulePaths/$1',
                'App\Controllers\Systems::updateDefaultDatabaseService',
                'App\Controllers\Systems::updateDefaultDomain',
                'App\Controllers\Systems::updateDefaultEmailService',
                'App\Controllers\Workspaces::requestSupportLogin/$1',
            ])
            ->delete();
    }

    public function down() {

    }

}
