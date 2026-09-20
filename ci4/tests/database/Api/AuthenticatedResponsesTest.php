<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * What the API hands back to someone who is signed in.
 *
 * Being signed in is not the same as being entitled to everything, and that is exactly the
 * gap here: several endpoints return stored credentials in plain text to any authenticated
 * user. Nothing enforces which fields may leave, so the answer has to be written down.
 *
 * Several tests below assert that a secret **is** returned. That is deliberate - they
 * record today's behaviour so the change is visible when those secrets stop being returned,
 * at which point they should be inverted rather than deleted.
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
     * second factor is configured, never the hash itself - the pattern the other endpoints
     * should follow.
     */
    public function testUsersReturnAnMfaFlagAndNotTheHash(): void {
        Fixtures::user(['username' => 'someone', 'mfa_secret_hash' => 'a-real-hash']);

        $user = $this->firstResourceNamed('users', 'username', 'someone');

        $this->assertArrayNotHasKey('mfa_secret_hash', $user);
        $this->assertTrue($user['has_mfa_secret_hash']);
    }

    /**
     * Fixed for registries when they became an entity of their own. The key, the client
     * secret and the password are written, never read back: the connection says whether each
     * is set, and that is all. It used to hand a whole GCP service account key to anyone
     * signed in.
     */
    public function testARegistryConnectionNeverReturnsItsSecrets(): void {
        Fixtures::containerRegistry([
            'name' => 'with-credentials',
            'gcloud_credentials' => '{"private_key":"THE-KEY"}',
            'azure_client_secret' => 'azure-secret',
            'harbor_password' => 'harbor-secret',
        ]);

        $registry = $this->firstResourceNamed('container_registries', 'name', 'with-credentials');

        foreach (['gcloud_credentials', 'azure_client_secret', 'harbor_password'] as $secret) {
            $this->assertArrayNotHasKey($secret, $registry);
            $this->assertTrue($registry["has_{$secret}"], $secret);
        }
    }

    /**
     * The same connection reached through an image, which is how the edit dialog loads
     * it. A relation is serialised by the related entity, so the rule holds there too.
     */
    public function testAnImageDoesNotLeakItsRegistrysSecrets(): void {
        $registry = Fixtures::containerRegistry(['harbor_password' => 'harbor-secret']);
        $image = Fixtures::containerImage(['container_registry_id' => $registry->id]);

        $body = $this->decode($this->signedIn()->get("container_images/{$image->id}?include=container_registry"));

        $this->assertStringNotContainsString('harbor-secret', json_encode($body));
        $this->assertTrue($body['resource']['container_registry']['has_harbor_password']);
    }

    /**
     * The plain-text secret that mattered most: this password opens the server every
     * customer's database lives on, and it came back to anyone signed in.
     */
    public function testDatabaseServicesDoNotReturnTheirPassword(): void {
        Fixtures::databaseService(['name' => 'with-password', 'pass' => 'hunter2']);

        $service = $this->firstResourceNamed('database_services', 'name', 'with-password');

        $this->assertArrayNotHasKey('pass', $service);
        $this->assertTrue($service['has_pass']);
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
     * The GitHub App has moved off the System row: the private key now lives
     * on the integration, and reaching it through an image - which is how the image dialog
     * loads it - must not bring it along.
     */
    public function testAnImageDoesNotLeakItsGithubAppsKeys(): void {
        $integration = Fixtures::githubIntegration();
        $image = Fixtures::containerImage(['github_integration_id' => $integration->id]);

        $body = $this->decode($this->signedIn()->get("container_images/{$image->id}?include=github_integration"));
        $text = json_encode($body);

        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $text);
        $this->assertStringNotContainsString('the-client-secret', $text);
        $this->assertStringNotContainsString('the-webhook-secret', $text);
        $this->assertTrue($body['resource']['github_integration']['has_private_key']);
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
