<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;
use App\Tests\Fakes\FakeIntegrations;

/**
 * An image's tags, read from its registry (FEAT-1).
 *
 * The endpoint is there to check a registry's credentials, so a registry that refuses must
 * say why. An empty list would read as "no tags" - the answer this used to give for both.
 */
class ContainerImagesApiTest extends ControllerTestCase {

    public function tearDown(): void {
        FakeIntegrations::uninstall();

        parent::tearDown();
    }

    /**
     * Oldest version first, whatever order the registry answered in, each with when it was
     * pushed - in UTC, since the three registries each write time their own way.
     */
    public function testTheTagsComeFromTheRegistryWithWhenTheyWerePushed(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = ['1.1.0', 'v1.0.0'];
        $fakes->tagPushTimes = ['1.1.0' => '2026-09-01T12:30:00.123+02:00'];
        $image = $this->imageInARegistry();

        $body = $this->decode($this->signedIn()->get("container-images/{$image->id}/tags"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame([
            ['name' => 'v1.0.0', 'pushed_at' => null],
            ['name' => '1.1.0', 'pushed_at' => '2026-09-01T10:30:00Z'],
        ], $body['resource']['tags']);
    }

    public function testARegistryThatRefusesIsReportedWithItsReason(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->failTagsWith = new \Exception('Harbor answered 401: unauthorized');
        $image = $this->imageInARegistry();

        $body = $this->decode($this->signedIn()->get("container-images/{$image->id}/tags"));

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString('Harbor answered 401: unauthorized', json_encode($body));
    }

    /**
     * Not an empty list either: there is no registry to have checked.
     */
    public function testAnImageWithoutARegistryIsReported(): void {
        $image = Fixtures::containerImage();

        $body = $this->decode($this->signedIn()->get("container-images/{$image->id}/tags"));

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString('no container registry', json_encode($body));
    }

    public function testAnUnknownImageIsReported(): void {
        $body = $this->decode($this->signedIn()->get('container-images/999999/tags'));

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString('unknown container image', json_encode($body));
    }

    private function imageInARegistry(): \App\Entities\ContainerImage {
        return Fixtures::containerImage(['container_registry_id' => Fixtures::containerRegistry()->id]);
    }

    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
