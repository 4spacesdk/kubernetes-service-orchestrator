<?php namespace App\Controllers;

use App\Core\ResourceController;

/**
 * The audit trail, to read: filter on `resource_type` and `resource_id` for one thing's history,
 * on `user_id` for one person's. Read only - see `Audit`.
 */
class AuditEvents extends ResourceController {

}
