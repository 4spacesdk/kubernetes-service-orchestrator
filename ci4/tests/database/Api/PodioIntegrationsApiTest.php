<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use App\Tests\Fakes\FakeIntegrations;

/**
 * The two custom endpoints on the Podio integrations controller, both of them reads that
 * go out to Podio.
 *
 * They exist for the dialogs that build a post update action: one lists the fields of the
 * Podio app so the user can pick one, the other fetches that field's options so the user
 * can pick a value. Neither writes anything, and neither can be reached from a test
 * without the fake standing in for Podio - which is the whole reason the gateway exists.
 *
 * What is worth holding is the guard. Both endpoints look the integration up and only
 * call Podio when it is really there, so an id that no longer exists answers empty
 * instead of taking an unconfigured integration - no client id, no app token - out to the
 * Podio API and failing there.
 */
class PodioIntegrationsApiTest extends ControllerTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    /**
     * The field list is Podio's, not kso's: nothing about a Podio app is stored here, so
     * the dialog is empty unless this endpoint reaches out and asks.
     */
    public function testTheFieldListComesFromPodio(): void {
        $fakes = FakeIntegrations::install();
        $fakes->podio()->fields = [
            ['id' => '42', 'name' => 'Status', 'type' => 'category'],
            ['id' => '43', 'name' => 'Released', 'type' => 'date'],
        ];
        $integration = Fixtures::podioIntegration();

        $body = $this->getJson("podio-integrations/{$integration->id}/fields");

        $this->assertSame(['Status', 'Released'], array_column($body['resources'], 'name'));
    }

    /**
     * An integration that is not there answers with an empty list rather than calling
     * Podio with a blank client id and app token. Podio would refuse that, and the dialog
     * would show an authentication error for what is really a deleted row.
     *
     * Podio is told to have fields here, so an empty answer can only come from the guard.
     */
    public function testAnUnknownIntegrationAnswersWithAnEmptyFieldListWithoutCallingPodio(): void {
        $fakes = FakeIntegrations::install();
        $fakes->podio()->fields = [['id' => '42', 'name' => 'Status', 'type' => 'category']];

        $body = $this->getJson('podio-integrations/999999/fields');

        $this->assertSame([], $body['resources']);
    }

    /**
     * **This list is not the list envelope the rest of the API uses.** Every other
     * collection carries `count` beside `resources` - see `ResourceEnvelopeApiTest` - and
     * this one sets `resources` by hand and never sets the count. A generated client that
     * reads the total off the envelope reads nothing here.
     *
     * Pinned as today's behaviour. Adding the count should break this test, not surprise
     * somebody halfway through a release.
     */
    public function testTheFieldListIsSentWithoutTheCountTheRestOfTheApiCarries(): void {
        FakeIntegrations::install();
        $integration = Fixtures::podioIntegration();

        $body = $this->getJson("podio-integrations/{$integration->id}/fields");

        $this->assertArrayHasKey('resources', $body);
        $this->assertArrayNotHasKey('count', $body);
    }

    /**
     * The details of one field, under `resource` rather than `resources` - it is a single
     * thing, and the dialog reads the options off it to offer the user a value to compare
     * against.
     */
    public function testTheDetailsOfOneFieldComeBackAsASingleResource(): void {
        FakeIntegrations::install();
        $integration = Fixtures::podioIntegration();

        $body = $this->getJson("podio-integrations/{$integration->id}/fields/42/details");

        $this->assertSame('42', $body['resource']['id']);
        $this->assertArrayHasKey('options', $body['resource']);
    }

    /**
     * A Podio field is addressed by an external id, which is a string - `status-42`, not
     * `7`. The route segment is `(.*)` and the parameter is typed `string` for that reason;
     * an integer anywhere along the way would turn every external id into 0 and the dialog
     * would show the wrong field's options.
     */
    public function testAFieldIsAddressedByItsPodioIdWhichIsNotANumber(): void {
        FakeIntegrations::install();
        $integration = Fixtures::podioIntegration();

        $body = $this->getJson("podio-integrations/{$integration->id}/fields/status-42/details");

        $this->assertSame('status-42', $body['resource']['id']);
    }

    /**
     * The same guard on the details endpoint, and it is the one that matters more: this is
     * reached with an id the dialog was handed earlier, so the row may well have been
     * deleted in between.
     */
    public function testAnUnknownIntegrationAnswersWithEmptyFieldDetails(): void {
        FakeIntegrations::install();

        $body = $this->getJson('podio-integrations/999999/fields/42/details');

        $this->assertSame([], $body['resource']);
    }

    /**
     * Both endpoints are reads of a third party's data through credentials kso holds, so
     * neither is public. `PublicSurfaceTest` pins the table; this proves the hook acts on
     * these two paths, which carry an id and would otherwise not be covered by it.
     */
    public function testNeitherEndpointAnswersWithoutAToken(): void {
        FakeIntegrations::install();
        $integration = Fixtures::podioIntegration();

        foreach (["podio-integrations/{$integration->id}/fields",
                  "podio-integrations/{$integration->id}/fields/42/details"] as $path) {
            try {
                $this->get($path);
                $this->fail("{$path} answered without a token");
            } catch (\RestExtension\Exceptions\UnauthorizedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // <editor-fold desc="Helpers">

    /**
     * @return array<string, mixed> the decoded response
     */
    private function getJson(string $path): array {
        $response = $this->signedIn()->get($path);

        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
