<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * `PUT /deployments/{id}/ingress` could never work: the controller called a method that only
 * exists on a workspace, so every call was a fatal error before it reached any validation. A
 * deployment has no domain of its own either - the ingress belongs to the workspace, and
 * `/workspaces/{id}/ingress` is the endpoint that does this. Nothing in the frontend called
 * it, but it was generated into the API client and published in the OpenAPI document, where
 * it read as something a client could use.
 */
class RemoveDeploymentIngressRoute extends Migration {

    public function up() {
        Database::connect()->table('api_routes')
            ->where('from', 'deployments/([0-9]+)/ingress')
            ->where('method', 'put')
            ->delete();
    }

    public function down() {

    }

}
