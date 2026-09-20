<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * `GET /api/jobby/run/{id}` - public, and a way for anyone who could reach the API to run
 * any single cron job in the installation, chosen by id.
 *
 * It is not how the job is run. `Jobby::index()` builds `php public/index.php jobby run <id>`
 * for each due job, and that reaches `Jobby::run()` through the **CLI** route declared in
 * `Config/Routes.php`. The HTTP route was written by the same initial migration in 2023 and
 * never called: nothing in the frontend, the generated client or the application refers to
 * it.
 *
 * So it is removed rather than closed. The route that has to stay reachable is `/api/jobby`,
 * which the chart's CronJob calls once a minute, and that one now has to carry `CRON_TOKEN`.
 */
class RemoveJobbyRunOverHttp extends Migration {

    public function up() {
        Database::connect()
            ->table('api_routes')
            ->where('method', 'get')
            ->where('from', 'jobby/run/([0-9]+)')
            ->delete();
    }

    public function down() {

    }

}
