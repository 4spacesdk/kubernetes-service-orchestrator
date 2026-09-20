<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Twenty routes answered `200` with an entirely empty body.
 *
 * Each of them points at a controller method with an empty body and `@ignore true` on it.
 * The annotation keeps the verb out of the route generator and out of swagger, but it does
 * not remove a row that is already in `api_routes` - and these were written by
 * `ApiRoute::addResourceController()` in migrations from 2023 onwards, before the methods
 * were emptied. Nothing has removed one since.
 *
 * What a caller got was worse than a refusal: `success()` is never reached in an empty
 * method, so there is no envelope at all - no `status`, no `error`, nothing. A client
 * reading that as "the write went through" is reading it the only way it can.
 *
 * The empty methods stay. They are what stops the resource controller's own `put()` from
 * replacing every column of a row - including a webhook's bearer token - if one of these
 * rows ever comes back.
 */
class RemoveRoutesToIgnoredMethods extends Migration {

    /**
     * Controller and method, as `api_routes.to` names them.
     */
    private const IgnoredMethods = [
        'App\Controllers\ContainerImages::put',
        'App\Controllers\DatabaseServices::put',
        'App\Controllers\DeploymentPackages::put',
        'App\Controllers\Deployments::post',
        'App\Controllers\Deployments::put',
        'App\Controllers\DeploymentSpecifications::put',
        'App\Controllers\Domains::put',
        'App\Controllers\EmailServices::put',
        'App\Controllers\Webhooks::put',
        'App\Controllers\Workspaces::post',
        'App\Controllers\Workspaces::put',
    ];

    public function up() {
        $db = Database::connect();

        foreach (self::IgnoredMethods as $method) {
            // Both the collection route and the one with an id: `to` is either the method
            // itself or the method plus `/$1`.
            $db->table('api_routes')
                ->groupStart()
                    ->where('to', $method)
                    ->orWhere('to', $method . '/$1')
                ->groupEnd()
                ->delete();
        }
    }

    public function down() {

    }

}
