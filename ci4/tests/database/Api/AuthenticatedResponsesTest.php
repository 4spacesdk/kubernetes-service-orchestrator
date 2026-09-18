<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * What the API hands back to someone who is signed in.
 *
 * Being signed in is not the same as being entitled to everything, and SEC-2 is exactly
 * that gap: several endpoints return stored credentials in plain text to any authenticated
 * user. Nothing enforces which fields may leave, so the answer has to be written down.
 *
 * Several tests below assert that a secret **is** returned. That is deliberate - they
 * record today's behaviour so the change is visible when SEC-2 is fixed, at which point
 * they should be inverted rather than deleted.
 */
class AuthenticatedResponsesTest extends ControllerTestCase {

    /**
     * The harness itself: the same request, refused without a token and answered with one.
     */
    public function testATokenIsWhatMakesTheDifference(): void {
        Fixtures::containerImage();

        try {
            $this->get('container_images');
            $this->fail('the endpoint answered without a token');
        } catch (UnauthorizedException) {
            $this->addToAssertionCount(1);
        }

        $response = $this->signedIn()->get('container_images');

        $this->assertSame(200, $response->response()->getStatusCode());
    }

    /**
     * How it is supposed to look. The user endpoint returns a flag saying whether a
     * second factor is configured, never the hash itself - the pattern SEC-2 asks the
     * other endpoints to follow.
     */
    public function testUsersReturnAnMfaFlagAndNotTheHash(): void {
        Fixtures::user(['username' => 'someone', 'mfa_secret_hash' => 'a-real-hash']);

        $user = $this->firstResourceNamed('users', 'username', 'someone');

        $this->assertArrayNotHasKey('mfa_secret_hash', $user);
        $this->assertTrue($user['has_mfa_secret_hash']);
    }

    /**
     * SEC-2. A whole GCP service account key, an Azure client secret and a Harbor
     * password, in plain text, to anyone who can sign in. Invert this when SEC-2 lands.
     */
    public function testContainerImagesStillReturnRegistryCredentials(): void {
        Fixtures::containerImage([
            'name' => 'with-credentials',
            'registry_provider_gcloud_credentials' => '{"private_key":"THE-KEY"}',
            'registry_provider_azure_client_secret' => 'azure-secret',
        ]);

        $image = $this->firstResourceNamed('container_images', 'name', 'with-credentials');

        $this->assertSame('{"private_key":"THE-KEY"}', $image['registry_provider_gcloud_credentials']);
        $this->assertSame('azure-secret', $image['registry_provider_azure_client_secret']);
    }

    /**
     * SEC-2, and the one that matters most: this password opens the server every
     * customer's database lives on.
     */
    public function testDatabaseServicesStillReturnTheirPassword(): void {
        Fixtures::databaseService(['name' => 'with-password', 'pass' => 'hunter2']);

        $service = $this->firstResourceNamed('database_services', 'name', 'with-password');

        $this->assertSame('hunter2', $service['pass']);
    }

    /**
     * The count and the page come back in the same envelope, and the frontend reads both.
     */
    public function testAListingCarriesItsCountAlongsideTheResources(): void {
        Fixtures::containerImage();

        $body = $this->decode($this->signedIn()->get('container_images'));

        $this->assertSame('OK', $body['status']);
        $this->assertArrayHasKey('count', $body);
        $this->assertCount($body['count'], $body['resources']);
    }

    /**
     * One row by id, rather than the listing. A different code path in the controller, and
     * the one a detail dialog uses.
     */
    public function testAsingleResourceComesBackUnderItsOwnKey(): void {
        $image = Fixtures::containerImage(['name' => 'just-this-one']);

        $body = $this->decode($this->signedIn()->get("container_images/{$image->id}"));

        $this->assertSame('just-this-one', $body['resource']['name']);
    }

    /**
     * The other half of the SEC-1 fix. `Settings` was the endpoint that leaked the GitHub
     * App private key without a token; `Systems::_setResource` is what stops the same
     * entity coming back on the response to a save, to anyone signed in.
     */
    public function testSavingTheSystemDoesNotHandBackTheGithubCredentials(): void {
        Fixtures::system([
            'hosting_provider' => \HostingProviders::Gke,
            'github_app_private_key' => '-----BEGIN RSA PRIVATE KEY-----',
            'github_app_client_secret' => 'client-secret',
            'github_app_webhook_secret' => 'webhook-secret',
        ]);

        $body = $this->decode(
            $this->withBodyFormat('json')
                ->signedIn()
                ->patch('systems/1', ['hosting_provider' => \HostingProviders::Eks])
        );

        $system = $body['resource'];

        $this->assertSame(\HostingProviders::Eks, $system['hosting_provider']);
        $this->assertArrayNotHasKey('github_app_private_key', $system);
        $this->assertArrayNotHasKey('github_app_client_secret', $system);
        $this->assertArrayNotHasKey('github_app_webhook_secret', $system);
    }

    // <editor-fold desc="Reading responses">

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    /**
     * The one resource in a listing whose $field is $value, so a test does not depend on
     * how many rows a fixture happened to leave behind.
     *
     * @return array<string, mixed>
     */
    private function firstResourceNamed(string $path, string $field, string $value): array {
        $body = $this->decode($this->signedIn()->get($path));

        foreach ($body['resources'] as $resource) {
            if (($resource[$field] ?? null) === $value) {
                return $resource;
            }
        }

        $this->fail("{$path} returned no resource with {$field} = {$value}");
    }

    // </editor-fold>

}
