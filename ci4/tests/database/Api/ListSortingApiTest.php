<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\Webhook;
use App\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use RestExtension\Exceptions\InvalidRequestException;

/**
 * Every field the lists in the web app sort by, asked of the API, and what happens to an
 * `ordering` that names something that is not there.
 *
 * The lists only send the fields they declare in `useListState({sortable})`, and those are
 * now checked against the model's columns on the way in - so **keep the provider below in
 * step with those declarations**: a column renamed in a migration fails here, not in the
 * browser.
 */
class ListSortingApiTest extends ControllerTestCase {

    // <editor-fold desc="What the lists ask for">

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

    // </editor-fold>

    // <editor-fold desc="An ordering that names something that is not there">

    /**
     * A field that is not a column is refused, rather than handed to the database.
     *
     * It used to go to `orderBy()` as written, and came back as a `DatabaseException`:
     * a 500 whose message is `Unknown column 'workspaces.nope' in 'order clause'` - the
     * statement kso failed on, reported to whoever typed the query string.
     */
    public function testAFieldThatIsNotAColumnIsRefused(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Cannot order by 'nope'");

        $this->signedIn()->get('workspaces?ordering=nope:asc');
    }

    /**
     * And so is a relation that is not one. This is a separate 500: the ORM's own
     * `Failed to find relation`, thrown out of `getRelation()` before any query is built.
     */
    public function testARelationThatDoesNotExistIsRefused(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('is not a relation');

        $this->signedIn()->get('workspaces?ordering=nope.name:asc');
    }

    /**
     * A direction that is not `asc` or `desc` is refused rather than dropped.
     *
     * This is the half that was quiet. CodeIgniter's `orderBy()` keeps a direction only
     * when it is `ASC` or `DESC` and replaces anything else with the empty string, so
     * `name_readable:sideways` answered `200 OK` sorted ascending - the wrong question
     * answered as if it were the right one, which is the same complaint as the label
     * filter that was half applied.
     */
    public function testADirectionThatIsNotAscOrDescIsRefused(): void {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("'sideways' is not");

        $this->signedIn()->get('workspaces?ordering=name_readable:sideways');
    }

    /**
     * The check is on the direction, not on its spelling: what the query string carries is
     * compared in lower case, and it is the normalised form that reaches the query builder.
     *
     * Green before the check existed too - CodeIgniter uppercases the direction itself -
     * so this one is here to catch the check refusing a direction the API used to accept,
     * not to prove the fix.
     */
    public function testTheDirectionIsReadWithoutRegardToCase(): void {
        Fixtures::workspace(['name_readable' => 'b', 'name_system' => 'b', 'namespace' => 'b']);
        Fixtures::workspace(['name_readable' => 'a', 'name_system' => 'a', 'namespace' => 'a']);
        Fixtures::workspace(['name_readable' => 'c', 'name_system' => 'c', 'namespace' => 'c']);

        $response = $this->signedIn()->get('workspaces?ordering=name_readable:DESC');
        $body = json_decode((string) $response->response()->getBody(), true);

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertSame(['c', 'b', 'a'], array_column($body['resources'] ?? [], 'name_readable'));
    }

    // </editor-fold>

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
