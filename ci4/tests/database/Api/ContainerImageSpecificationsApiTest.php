<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * Which specifications use a container image, and as what - what its row in the list opens, or
 * offers to make when there is none.
 */
class ContainerImageSpecificationsApiTest extends ControllerTestCase {

    public function testEachSpecificationIsListedOnceWithEveryUseOfTheImage(): void {
        $image = Fixtures::containerImage(['name' => 'backend']);
        $api = Fixtures::deploymentSpecification(['name' => 'api', 'container_image_id' => $image->id, 'enable_database' => true, 'database_migration_container_image_id' => $image->id]);
        $worker = Fixtures::deploymentSpecification(['name' => 'worker', 'container_image_id' => Fixtures::containerImage(['url' => 'registry.example.org/other'])->id]);
        $sidecar = Fixtures::initContainer(['name' => 'proxy', 'container_image_id' => $image->id, 'is_sidecar' => true]);
        Fixtures::specificationInitContainer(['deployment_specification_id' => $worker->id, 'init_container_id' => $sidecar->id]);
        Fixtures::specificationCronJob([
            'deployment_specification_id' => $worker->id,
            'k8s_cron_job_id' => Fixtures::cronJob(['container_image_id' => $image->id])->id,
        ]);

        $uses = $this->usesOf($image->id);

        $this->assertSame([
            ['id' => (int) $api->id, 'name' => 'api', 'roles' => ['workload', 'migration job']],
            ['id' => (int) $worker->id, 'name' => 'worker', 'roles' => ['sidecar', 'cron job']],
        ], $uses);
    }

    public function testAnImageNoSpecificationUsesHasNone(): void {
        $image = Fixtures::containerImage();

        $this->assertSame([], $this->usesOf($image->id));
    }

    /**
     * @return list<array{id: int, name: string, roles: list<string>}>
     */
    private function usesOf(int|string $imageId): array {
        $body = json_decode((string) $this->signedIn()->get("container_images/{$imageId}")->response()->getBody(), true);

        return json_decode($body['resource']['specification_uses'], true);
    }

}
