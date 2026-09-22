<?php namespace App\Entities;

use App\Core\Entity;

/**
 * One row of the audit trail - see `App\Libraries\Audit\Audit`, which is the only thing that
 * writes them. Read through the API.
 *
 * `created` and `id` are not listed here: the model generator adds them to every entity, and a
 * second `created` in the docblock became a duplicate property in the generated TypeScript.
 *
 * @property int $user_id
 * @property string $client_id
 * @property string $ip_address
 * @property string $source ui, api, web, cron, queue or cli - see AuditContext
 * @property string $action created, updated, deleted, relation_added, relation_removed, or a named action such as workspace.deploy
 * @property string $resource_type
 * @property int $resource_id
 * @property string $resource_name
 * @property string $details JSON: `changes` (field => [before, after]), `relation`, or what the action adds
 */
class AuditEvent extends Entity {

}
