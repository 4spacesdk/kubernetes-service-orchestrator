<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\TestResponse;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * The plain REST reads, across every resource that has them.
 *
 * `ResourceControllerTrait::get()` is one method shared by every resource controller, and
 * `ResourceModelTrait::restGet()` is one method shared by every model - but each model
 * brings its own `preRestGet()`, `postRestGet()` and `appleRestGetManyRelations()`, and
 * those run only on this path. Nothing reached them before this file: the controller tests
 * all hit the *custom* endpoints, so every model had exactly three covered statements, the
 * three `return true` a write test walks past.
 *
 * The list of resources is read out of `api_routes` rather than written here, for the same
 * reason `PublicSurfaceTest` reads it: the table is what the router acts on. A resource
 * added later is swept without anyone remembering to add it, and a resource that
 * disappears fails `testTheSweepStillCoversEveryResourceItHasARowFor()` instead of quietly
 * being skipped.
 */
class RestGetSweepTest extends ControllerTestCase {

    /**
     * Field names that must never leave the server, whatever resource they are on.
     *
     * Names, not values: a test that looked for the value it just wrote would say nothing
     * about a column no fixture happens to fill.
     */
    private const SECRET_FIELDS = [
        'registry_provider_gcloud_credentials',
        'registry_provider_azure_client_secret',
        'registry_provider_harbor_password',
        'pass',
        'password',
        'client_secret',
        'app_token',
        'auth_bearer_token',
        'mfa_secret_hash',
        'github_app_private_key',
        'github_app_client_secret',
        'github_app_webhook_secret',
    ];

    // <editor-fold desc="The route table is the list">

    /**
     * Every resource the table routes a plain collection read to.
     *
     * `to` ending in `::get` with nothing after it is what separates a collection read
     * from a custom endpoint on the same controller - `Deployments::getStatus/$1` is a
     * different method and a different test's problem.
     *
     * @return list<string>
     */
    private function collectionResources(): array {
        $rows = $this->db->table('api_routes')
            ->select('`from`, `to`', false)
            ->where('method', 'get')
            ->like('to', '::get', 'before')
            ->get()
            ->getResultArray();

        $resources = array_column($rows, 'from');
        sort($resources);

        return $resources;
    }

    /**
     * Every resource the table routes a numeric by-id read to.
     *
     * `o_auth_clients` also has a `(.*)` variant, because its primary key is a client id
     * rather than an integer. Only the numeric routes are swept here; the other one is a
     * different shape and is called out in `testAResourceKeyedByAStringHasNoReachableNumericById()`.
     *
     * @return list<string>
     */
    private function byIdResources(): array {
        $rows = $this->db->table('api_routes')
            ->select('`from`, `to`', false)
            ->where('method', 'get')
            ->like('to', '::get/$1', 'before')
            ->like('from', '/([0-9]+)', 'before')
            ->get()
            ->getResultArray();

        $resources = array_map(
            static fn (array $row) => substr($row['from'], 0, strpos($row['from'], '/')),
            $rows
        );
        sort($resources);

        return $resources;
    }

    // </editor-fold>

    // <editor-fold desc="The sweep">

    /**
     * The shape every generated client is built against, on every collection route.
     *
     * `count` is its own field rather than something the caller derives from `resources`,
     * so the two have to agree: a paginated list that trusts one and pages with the other
     * shows the wrong total.
     *
     * Deviations are collected rather than asserted one at a time, so one run names every
     * endpoint that is out of line instead of stopping at the first.
     */
    public function testEveryCollectionRouteAnswersTheSameEnvelope(): void {
        $this->rowsForEveryResource();

        $deviations = [];

        foreach ($this->collectionResources() as $resource) {
            $this->forgetTheLastRequest();
            $body = $this->decode($this->signedIn()->get($resource));

            if (($body['status'] ?? null) !== 'OK') {
                $deviations[$resource] = 'status was ' . json_encode($body['status'] ?? null);
                continue;
            }
            if (!is_array($body['resources'] ?? null) || !array_is_list($body['resources'])) {
                $deviations[$resource] = 'resources was not a list';
                continue;
            }
            if (!array_key_exists('count', $body)) {
                $deviations[$resource] = 'no count in the envelope';
                continue;
            }
            if ($body['count'] !== count($body['resources'])) {
                $deviations[$resource] = "count {$body['count']} against " . count($body['resources']) . ' rows';
            }
        }

        $this->assertSame(
            [
                // `Environments` is not a resource controller. It answers from the
                // `Environments` enum, sets `resources` by hand and never sets `count`,
                // so a client that reads the count off this envelope reads whatever the
                // field means when it is absent. Pinned, not endorsed - see the report.
                'environments' => 'no count in the envelope',
            ],
            $deviations
        );
    }

    /**
     * A collection is not readable without a token.
     *
     * This is the `api_routes.is_public` half of SEC-1 held against every plain read at
     * once: `PublicSurfaceTest` asserts the column, this asserts that a real request is
     * actually stopped by it. No token is ever sent in this test, so nothing here can be
     * answered by a session another test left behind.
     */
    public function testNoCollectionRouteAnswersWithoutAToken(): void {
        $answered = [];

        foreach ($this->collectionResources() as $resource) {
            try {
                $response = $this->get($resource);
                $answered[$resource] = $response->response()->getStatusCode();
            } catch (UnauthorizedException) {
                $this->addToAssertionCount(1);
            }

            $this->forgetTheLastRequest();
        }

        $this->assertSame([], $answered, 'these answered an unauthenticated caller');
    }

    /**
     * A by-id read answers one resource, under `resource`, carrying the id that was asked
     * for - never a one-element `resources`, which the generated clients type differently.
     */
    public function testEveryByIdRouteAnswersTheRowThatWasAskedFor(): void {
        $ids = $this->rowsForEveryResource();

        $deviations = [];

        foreach ($this->byIdResources() as $resource) {
            if (($ids[$resource] ?? null) === null) {
                continue;
            }

            $id = $ids[$resource];
            $this->forgetTheLastRequest();
            $body = $this->decode($this->signedIn()->get("{$resource}/{$id}"));

            if (($body['status'] ?? null) !== 'OK') {
                $deviations[$resource] = 'status was ' . json_encode($body['status'] ?? null);
                continue;
            }
            if (!is_array($body['resource'] ?? null)) {
                $deviations[$resource] = 'no single resource in the envelope';
                continue;
            }
            if ((int) ($body['resource']['id'] ?? 0) !== (int) $id) {
                $deviations[$resource] = "asked for {$id}, got " . json_encode($body['resource']['id'] ?? null);
                continue;
            }
            if (array_key_exists('resources', $body)) {
                $deviations[$resource] = 'a single read also sent a collection';
            }
        }

        $this->assertSame([], $deviations);
    }

    /**
     * What a by-id read does with an id that is not there, pinned across all twenty-two at
     * once - the question FEAT-9 asks. Twenty-one of them agree, one does not, and none of
     * them refuses.
     *
     * `ResourceControllerTrait::get()` never asks whether the row was found. It calls
     * `_setResource($items->first())`, and `first()` on a collection that loaded nothing
     * hands back the empty entity itself rather than null - so `toArray()` walks the
     * entity's declared fields and produces **a complete object with every value null**.
     * The caller gets `200 OK` and a resource that looks like a row, with an id of null.
     *
     * Two things follow, and both are reported rather than fixed:
     *
     * - a client cannot tell a missing row from a real one by the status line, and a
     *   deserialiser that types `id` as an integer gets null;
     * - the object is the entity's full field list, so a by-id read of an id that does not
     *   exist **enumerates every column name of every resource**, to any signed-in caller.
     *
     * The assertion is on what comes back non-null, so it does not have to spell out
     * thirty column names per resource to stay loud: the day a not-found read starts
     * refusing, `resource` stops being an object and this fails.
     */
    public function testAnIdThatDoesNotExistIsAnsweredWithAHollowResourceAndNotARefusal(): void {
        $missing = 999666333;
        $answers = [];

        foreach ($this->byIdResources() as $resource) {
            $this->forgetTheLastRequest();
            $response = $this->signedIn()->get("{$resource}/{$missing}");
            $body = $this->decode($response);

            $this->assertSame(200, $response->response()->getStatusCode(), "{$resource} refused with a status code");
            $this->assertSame('OK', $body['status'] ?? null, "{$resource} refused in the envelope");

            $answers[$resource] = $this->describeWhatCameBack($body);
        }

        $expected = array_fill_keys($this->byIdResources(), 'a resource with every field null');

        // Two entities compute a field rather than reading one, so it has a value even on
        // a row that was never loaded.
        $expected['deployment_specifications'] = 'a resource whose only non-null field is deploymentSteps';
        $expected['users'] = 'a resource whose only non-null field is has_mfa_secret_hash';

        // `Environments` is not a resource controller and its `get()` takes no id, but the
        // route table sends `environments/([0-9]+)` to it anyway. PHP accepts the extra
        // argument silently, so asking for one environment by id hands back all of them -
        // under `resources`, which is not the field a by-id caller reads. Reported, not
        // fixed: the id is not wrong here, it is not looked at.
        $expected['environments'] = 'a collection, not a resource';

        $this->assertSame($expected, $answers);
    }

    /**
     * `o_auth_clients` is keyed by `client_id`, a string, and the table carries both a
     * `([0-9]+)` route and a `(.*)` route to the same method. The numeric one can never
     * match a real client id, so the only thing it can ever answer is the hollow resource
     * above - which is why the by-id sweep skips it rather than pretending to read a row.
     *
     * Held here so that the sweep's silence about this resource is deliberate rather than
     * an oversight.
     */
    public function testAResourceKeyedByAStringHasNoReachableNumericById(): void {
        $primaryKey = (new \App\Models\OAuthClientModel())->getPrimaryKey();

        $this->assertSame('client_id', $primaryKey);

        $body = $this->decode($this->signedIn()->get('o_auth_clients'));
        $this->assertNotEmpty($body['resources'], 'the sign-in fixture client should be in the list');

        foreach ($body['resources'] as $client) {
            $this->assertArrayNotHasKey(
                'id',
                $client,
                'an oauth client has no integer id for the numeric route to match'
            );
        }
    }

    // </editor-fold>

    // <editor-fold desc="SEC-2">

    /**
     * Every collection read, swept for field names that are credentials.
     *
     * This is the assertion the rest of the file exists to make possible. Being signed in
     * is not being entitled to a stored password, and nothing in the stack decides which
     * fields may leave a resource - `allToArray()` sends the row.
     *
     * **The list below is today's behaviour, pinned so the fix is visible.** Every pair in
     * it is a credential handed in cleartext to any authenticated caller. Fixing SEC-2
     * breaks this test, and that failure is the fix landing: the entry comes out of the
     * list, it does not get added to.
     *
     * Only non-empty values count. A column that happens to be blank proves nothing about
     * whether the field is sent, which is why `rowsForEveryResource()` fills each one.
     */
    public function testEveryCollectionReadIsSweptForSecretsAndTheseStillComeBack(): void {
        $this->rowsForEveryResource();

        $leaks = [];

        foreach ($this->collectionResources() as $resource) {
            $this->forgetTheLastRequest();
            $body = $this->decode($this->signedIn()->get($resource));

            foreach ($body['resources'] ?? [] as $row) {
                foreach (self::SECRET_FIELDS as $field) {
                    if (($row[$field] ?? '') !== '' && $row[$field] !== null) {
                        $leaks[$resource][$field] = true;
                    }
                }
            }
        }

        $leaks = array_map(static fn (array $fields) => array_keys($fields), $leaks);
        ksort($leaks);
        foreach ($leaks as &$fields) {
            sort($fields);
        }
        unset($fields);

        $this->assertSame([
            'container_images' => [
                'registry_provider_azure_client_secret',
                'registry_provider_gcloud_credentials',
                'registry_provider_harbor_password',
            ],
            'database_services' => ['pass'],
            'email_services' => ['pass'],
            'o_auth_clients' => ['client_secret'],
            'podio_integrations' => ['app_token', 'client_secret'],
            'webhooks' => ['auth_bearer_token'],
        ], $leaks, 'SEC-2: a credential stopped coming back, or a new one started');
    }

    /**
     * The one resource that already does it right, kept next to the list above so the
     * shape of the fix is not a matter of taste. `users` stores a bcrypt hash and an MFA
     * secret hash and sends neither - `User::toArray()` drops them and adds a flag.
     */
    public function testTheResourceThatAlreadyWithholdsItsSecretsStillDoes(): void {
        Fixtures::user(['username' => 'sweep-user', 'mfa_secret_hash' => 'a-real-hash']);

        $body = $this->decode($this->signedIn()->get('users'));

        foreach ($body['resources'] as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('mfa_secret_hash', $user);
        }
    }

    // </editor-fold>

    // <editor-fold desc="The guard on the sweep itself">

    /**
     * A resource that leaves the route table has to fail rather than quietly stop being
     * swept.
     *
     * The sweep reads its list from `api_routes`, which is what makes a new resource
     * covered for free - and it is also what would make a deleted route disappear without
     * a word. The row builders below are the counterweight: each one names a resource, and
     * a name that is no longer routed is a failure here.
     */
    public function testTheSweepStillCoversEveryResourceItHasARowFor(): void {
        $collections = $this->collectionResources();

        foreach (array_keys($this->rowsForEveryResource()) as $resource) {
            $this->assertContains($resource, $collections, "{$resource} is no longer routed");
        }

        $this->assertCount(22, $collections, 'the number of plain collection reads changed');
        $this->assertCount(22, $this->byIdResources(), 'the number of plain by-id reads changed');
    }

    /**
     * The only resource the sweep reads without a row of its own, said out loud.
     *
     * `environments` has no table: it answers from the `Environments` enum, which is never
     * empty, so there is nothing for a row builder to write and nothing missing from what
     * the sweep sees.
     */
    public function testTheOnlyResourceWithoutARowBuilderIsTheOneWithoutATable(): void {
        $withoutARow = array_values(array_diff(
            $this->collectionResources(),
            array_keys($this->rowsForEveryResource())
        ));

        $this->assertSame(['environments'], $withoutARow);
    }

    // </editor-fold>

    // <editor-fold desc="Rows">

    /**
     * One row per resource, with every credential column filled.
     *
     * A list response with no rows in it says nothing: `restGet()` skips `postRestGet()`
     * and `appleRestGetManyRelations()` entirely when the collection is empty, and a
     * secret sweep over zero rows passes by default.
     *
     * The values are inventions, never anything from the environment. They are recognisable
     * on purpose so a failure message says which row it came from.
     *
     * A resource whose row has no integer id maps to null: it is read in the collection
     * sweep, and skipped by the by-id sweep because no numeric route can reach it.
     *
     * @return array<string, int|null> resource path to the id of its row
     */
    private function rowsForEveryResource(): array {
        if ($this->ids !== []) {
            return $this->ids;
        }

        $deployment = Fixtures::deployment();

        $this->ids = [
            'auto_updates' => $this->insert('auto_updates', [
                'deployment_id' => $deployment->id,
                'image' => 'registry.example.org/test/app',
                'previous_tag' => '1.0.0',
                'next_tag' => '1.1.0',
                'is_approved' => 0,
                'approved_date' => '',
            ]),
            'container_images' => Fixtures::containerImage([
                'registry_provider_gcloud_credentials' => '{"private_key":"SWEEP-GCLOUD-KEY"}',
                'registry_provider_azure_client_secret' => 'sweep-azure-secret',
                'registry_provider_harbor_password' => 'sweep-harbor-password',
            ])->id,
            'database_services' => Fixtures::databaseService(['pass' => 'sweep-db-password'])->id,
            'deployment_packages' => Fixtures::deploymentPackage()->id,
            'deployment_specifications' => Fixtures::deploymentSpecification()->id,
            'deployments' => $deployment->id,
            'domains' => Fixtures::domain()->id,
            'email_services' => Fixtures::emailService(['pass' => 'sweep-mail-password'])->id,
            'gateways' => Fixtures::gateway()->id,
            'init_containers' => Fixtures::initContainer()->id,
            'k8s_cron_jobs' => Fixtures::cronJob()->id,
            'k_native_min_scale_schedules' => Fixtures::minScaleSchedule()->id,
            'migration_jobs' => $this->insert('migration_jobs', [
                'deployment_id' => $deployment->id,
                'status' => 'completed',
                'log' => 'swept',
                'command' => 'php spark migrate',
                'image' => 'registry.example.org/test/app:1.0.0',
                'created' => date('Y-m-d H:i:s'),
            ]),
            // Keyed by `client_id`, so there is no integer id for the numeric by-id route
            // to match. The sign-in fixture's own client row is on another connection and
            // has an empty secret, so the sweep gets its own row with one filled in.
            'o_auth_clients' => $this->insertWithoutAnId('oauth_clients', [
                'client_id' => 'sweep-client',
                'client_secret' => 'sweep-client-secret',
                'redirect_uri' => '',
                'grant_types' => 'client_credentials',
                'scope' => '',
                'user_id' => '',
            ]),
            'podio_integrations' => Fixtures::podioIntegration([
                'client_secret' => 'sweep-podio-secret',
                'app_token' => 'sweep-podio-token',
            ])->id,
            'post_update_actions' => Fixtures::postUpdateAction()->id,
            'rbac_permissions' => $this->insert('rbac_permissions', [
                'name' => 'sweep.read',
                'description' => 'swept',
            ]),
            'rbac_roles' => $this->insert('rbac_roles', [
                'identifier' => 'sweep',
                'name' => 'Sweep',
                'description' => 'swept',
            ]),
            'users' => $this->signedInUserId(),
            'webhooks' => $this->insert('webhooks', [
                'type' => \WebHookTypes::All()[0],
                'name' => 'sweep-hook',
                'url' => 'https://hook.test/sweep',
                'content_type' => 'application/json',
                'auth_bearer_token' => 'sweep-bearer-token',
                'http_method' => 'POST',
                'created' => date('Y-m-d H:i:s'),
            ]),
            'workspaces' => Fixtures::workspace()->id,
        ];

        ksort($this->ids);

        return $this->ids;
    }

    /** @var array<string, int|null> */
    private array $ids = [];

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): int {
        $this->db->table($table)->insert($row);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertWithoutAnId(string $table, array $row): ?int {
        $this->db->table($table)->insert($row);

        return null;
    }

    // </editor-fold>

    // <editor-fold desc="Reading responses">

    /**
     * What a by-id read answered, in one line, so the pin above can name twenty-two
     * endpoints without spelling out every column of every entity.
     *
     * @param array<string, mixed> $body
     */
    private function describeWhatCameBack(array $body): string {
        if (!is_array($body['resource'] ?? null)) {
            return isset($body['resources']) ? 'a collection, not a resource' : 'no resource at all';
        }

        if ($body['resource'] === []) {
            return 'an empty object';
        }

        $set = array_keys(array_filter($body['resource'], static fn ($value) => $value !== null));

        return $set === []
            ? 'a resource with every field null'
            : 'a resource whose only non-null field is ' . implode(', ', $set);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true) ?? [];
    }

    // </editor-fold>

}
