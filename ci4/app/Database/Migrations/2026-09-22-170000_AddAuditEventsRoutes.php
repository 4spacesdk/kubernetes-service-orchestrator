<?php namespace App\Database\Migrations;

use App\Controllers\AuditEvents;
use CodeIgniter\Database\Migration;
use RestExtension\Entities\ApiRoute;

/**
 * The audit trail through the API, to read only.
 */
class AddAuditEventsRoutes extends Migration {

    public function up() {
        ApiRoute::addResourceControllerGet(AuditEvents::class);
    }

    public function down() {

    }

}
