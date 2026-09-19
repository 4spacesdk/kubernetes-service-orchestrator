<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Controllers\Systems;
use App\Entities\System;
use App\Fixtures;
use DebugTool\Data;

/**
 * The Systems endpoints - the System page's save, and what comes back from it.
 *
 * The System row held the GitHub App credentials until INT-2 moved them to
 * `GithubIntegration`, and SEC-1 was them going out over the API. The fix was
 * `System::toPublicArray()`, an allow list, which every System response goes through.
 *
 * **An allow list is only worth what its test asserts.** Checking that three named
 * credentials are absent proves nothing about the fourth one somebody adds next year, so
 * the tests here pin the list of keys exactly, and separately reject anything that reads
 * like a credential whatever it is called. A new field on the System entity is then a
 * deliberate decision, made here, rather than a leak nobody noticed.
 *
 * `PublicSurfaceTest` holds the same line for the unauthenticated half, `GET /settings`.
 * This is the half behind a token - which is where SEC-1's second path was.
 */
class SystemsApiTest extends ControllerTestCase {

    /**
     * Exactly the six fields the System page is allowed to see, in the order the allow
     * list gives them.
     *
     * Pinned rather than derived. If this fails because a field was added to
     * `toPublicArray()`, the question to answer before changing it is whether that field
     * is a credential.
     */
    private const PUBLIC_FIELDS = [
        'id',
        'is_network_nginx_ingress_supported',
        'is_network_istio_supported',
        'is_network_contour_supported',
        'is_network_gateway_api_supported',
        'hosting_provider',
    ];

    /**
     * Saving the System page answers with the row, and the browser needs that answer to
     * redraw the form. It must be the allow list and nothing beyond it.
     */
    public function testSavingTheSystemAnswersWithTheAllowListAndNothingElse(): void {
        $this->aFullyConfiguredSystem();

        $system = $this->save(['hosting_provider' => \HostingProviders::Eks])['resource'];

        $this->assertSame(self::PUBLIC_FIELDS, array_keys($system));
        $this->assertSame(\HostingProviders::Eks, $system['hosting_provider']);
    }

    /**
     * The save actually reaches the database, not just the response.
     *
     * Every other test here reads the response, and the response is not evidence that
     * anything was written: `ResourceEntityTrait::patch()` populates the entity, asks
     * `SystemModel::isRestUpdateAllowed()`, and on a refusal **returns that populated
     * entity anyway** - unsaved, with a line in the debug log and `success()` on top. The
     * caller is answered `200 OK` carrying the values it just sent, and the row is
     * unchanged.
     *
     * So `isRestUpdateAllowed()` returning `false` was invisible to this file: mutating it
     * left all eight tests green. This one reads the row back through a fresh entity, which
     * is the only question that matters about a save. It is the same family as FEAT-9 - the
     * API says OK when it did nothing - and the reason it is worth a test of its own is
     * that the response cannot tell you.
     */
    public function testSavingTheSystemChangesTheRowAndNotJustTheAnswer(): void {
        $this->aFullyConfiguredSystem(['hosting_provider' => \HostingProviders::Gke]);

        $this->save(['hosting_provider' => \HostingProviders::Eks]);

        $stored = new System();
        $stored->find(1);

        $this->assertSame(\HostingProviders::Eks, $stored->hosting_provider);
    }

    /**
     * The same guarantee stated by shape rather than by name, so a credential added to the
     * System entity under a name nobody on this list thought of is still caught.
     */
    public function testSavingTheSystemNeverAnswersWithAnythingThatLooksLikeACredential(): void {
        $this->aFullyConfiguredSystem();

        $body = (string) $this->saveResponse(['hosting_provider' => \HostingProviders::Gke]);

        foreach (array_keys(json_decode($body, true)['resource']) as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/secret|private_key|credential|password|token/i',
                $key,
                "the system save returned a field called {$key}"
            );
        }
    }

    /**
     * Types, which the allow list casts by hand because reading a property gives the raw
     * database value.
     *
     * A boolean column arrives as the string `"0"`, and `"0"` is true in JavaScript - so a
     * flag returned unparsed turns every network type on in the UI the moment the System
     * page is saved, offering Istio and Contour on a cluster that has neither.
     */
    public function testTheSavedSystemComesBackTypedForTheBrowser(): void {
        $this->aFullyConfiguredSystem(['is_network_istio_supported' => false]);

        $system = $this->save(['is_network_nginx_ingress_supported' => true])['resource'];

        $this->assertTrue($system['is_network_nginx_ingress_supported']);
        $this->assertFalse($system['is_network_istio_supported']);
        $this->assertIsInt($system['id']);
        $this->assertIsString($system['hosting_provider']);
    }

    /**
     * `PATCH /systems` without an id is a route of its own, and it answers with a list
     * rather than one row. It went through `_setResources()`, which was not overridden, and
     * handed the GitHub App private key to anyone holding a token - SEC-1's third way out.
     */
    public function testTheBulkSaveRouteAnswersWithTheAllowListToo(): void {
        $this->aFullyConfiguredSystem();

        $body = json_decode((string) $this->withBodyFormat('json')->signedIn()->patch('systems', [
            ['id' => 1, 'hosting_provider' => \HostingProviders::Eks],
        ])->response()->getBody(), true);

        $this->assertSame(self::PUBLIC_FIELDS, array_keys($body['resources'][0]));
        $this->assertSame(\HostingProviders::Eks, $body['resources'][0]['hosting_provider']);
    }

    /**
     * Every route that reaches this controller, exactly.
     *
     * The four inherited REST verbs are switched off with `@ignore true`. Turning any of
     * them back on is a security decision, and this is where it is made.
     *
     * The three `default_*` routes are listed because they exist in the table, not because
     * they work: they point at `updateDefaultEmailService`, `updateDefaultDatabaseService`
     * and `updateDefaultDomain`, none of which is a method on this controller. Calling one
     * is a 404, as the next test shows.
     */
    public function testTheSystemRowIsOnlyReachableThroughPatch(): void {
        $rows = $this->db->table('api_routes')
            ->select('method, `from`', false)
            ->like('from', 'systems', 'after')
            ->get()
            ->getResultArray();

        $actual = array_map(
            static fn (array $row) => strtolower($row['method']) . ' ' . $row['from'],
            $rows
        );
        sort($actual);

        $this->assertSame([
            'patch systems',
            'patch systems/([0-9]+)',
            'put systems/default_database_service_id',
            'put systems/default_domain_id',
            'put systems/default_email_service_id',
        ], $actual);
    }

    /**
     * The three `default_*` routes are dead. A migration registered them for methods that
     * were never written, so the row is in `api_routes`, swagger advertises the endpoint,
     * and the request ends in the framework's "controller method is not found".
     *
     * Worth a test because the table is the API's documentation: a client generated from
     * it offers three calls that cannot work, and nothing else in the codebase says so.
     */
    public function testTheDefaultSelectionRoutesPointAtMethodsThatDoNotExist(): void {
        foreach (['default_domain_id', 'default_database_service_id', 'default_email_service_id'] as $route) {
            try {
                $this->withBodyFormat('json')->signedIn()->put("systems/{$route}", ['id' => 1]);
                $this->fail("systems/{$route} answered, so the method now exists");
            } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
                $this->assertStringContainsString('Controller method is not found', $e->getMessage());
            }
        }
    }

    /**
     * The override narrows to `System`; it does not replace the base class.
     *
     * Everything else in kso is served by the same `_setResource()` on `ResourceController`
     * and has to keep coming back whole - none of those entities has an allow list, and a
     * guard written the other way round would empty every response in the application.
     * Called directly because the Systems routes only ever carry a System through here.
     */
    public function testAnEntityThatIsNotTheSystemIsStillSerialisedInFull(): void {
        $domain = Fixtures::domain(['name' => 'kso.example.org']);

        (new Systems())->_setResource($domain);

        $this->assertSame('kso.example.org', Data::get('resource')['name']);
        $this->assertSame('test-cert', Data::get('resource')['certificate_name']);
    }

    // <editor-fold desc="Helpers">

    /**
     * The System row as a real installation has it.
     *
     * @param array<string, mixed> $overrides
     */
    private function aFullyConfiguredSystem(array $overrides = []): void {
        Fixtures::system(array_merge([
            'hosting_provider' => \HostingProviders::Gke,
            'is_network_gateway_api_supported' => true,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed> the decoded response
     */
    private function save(array $values): array {
        return json_decode((string) $this->saveResponse($values), true);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function saveResponse(array $values): string {
        return (string) $this->withBodyFormat('json')
            ->signedIn()
            ->patch('systems/1', $values)
            ->response()
            ->getBody();
    }

    // </editor-fold>

}
