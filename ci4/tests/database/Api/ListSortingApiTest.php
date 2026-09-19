<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every field the lists in the web app sort by (LIST-5), asked of the API.
 *
 * `ordering` is not checked by the API: a field that is not a column is a database error,
 * and a direction that is not `asc` or `desc` answers an empty list. The lists only send
 * the fields they declare in `useListState({sortable})`, so those have to hold. **Keep this
 * in step with those declarations** - a column renamed in a migration fails here, not in
 * the browser.
 */
class ListSortingApiTest extends ControllerTestCase {

    #[DataProvider('whatTheListsSortBy')]
    public function testEverySortTheListsOfferIsAnswered(string $resource, string $field): void {
        if (str_contains($resource, '{webhook}')) {
            $resource = str_replace('{webhook}', (string) $this->webhook()->id, $resource);
        }

        foreach (['asc', 'desc'] as $direction) {
            $response = $this->signedIn()->get("{$resource}?ordering={$field}:{$direction}&limit=50&offset=0");
            $body = json_decode((string) $response->response()->getBody(), true);

            $this->assertSame(200, $response->response()->getStatusCode(), "{$resource} by {$field}");
            $this->assertSame('OK', $body['status'] ?? null, "{$resource} by {$field}");
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function whatTheListsSortBy(): array {
        $lists = [
            'auto_updates' => ['id', 'deployment.name', 'is_approved'],
            'container_registries' => ['name', 'provider', 'events_enabled'],
            'github_integrations' => ['name', 'organization', 'slug', 'installation_id'],
            'o_auth_clients' => ['client_id'],
            'podio_integrations' => ['name', 'client_id', 'app_id'],
            'webhooks/{webhook}/deliveries' => ['id', 'url', 'method', 'response_code'],
            'webhooks' => ['name', 'type', 'url', 'http_method'],
            'migration_jobs' => ['id', 'status', 'deployment.name', 'started', 'ended'],
            'container_images' => ['name', 'url', 'pull_secret', 'container_registry.name', 'version_control_provider'],
            'database_services' => ['name', 'driver'],
            'deployment_packages' => ['name'],
            'deployment_specifications' => ['name', 'workload_type', 'network_type', 'enable_database', 'enable_rbac'],
            'deployments' => ['name', 'namespace', 'status', 'version', 'last_updated'],
            'domains' => ['name'],
            'email_services' => ['name'],
            'gateways' => ['name', 'gateway_class_name', 'namespace'],
            'users' => ['name', 'username'],
            'workspaces' => ['name_readable', 'namespace', 'status'],
        ];

        $cases = [];
        foreach ($lists as $resource => $fields) {
            foreach ($fields as $field) {
                $cases["{$resource} by {$field}"] = [$resource, $field];
            }
        }
        return $cases;
    }

    private function webhook(): Webhook {
        $webhook = new Webhook();
        $webhook->type = \WebHookTypes::Workspace_Deployed;
        $webhook->name = 'sorted';
        $webhook->url = 'https://subscriber.invalid/hook';
        $webhook->http_method = 'post';
        $webhook->content_type = 'application/json';
        $webhook->save();
        return $webhook;
    }

}
