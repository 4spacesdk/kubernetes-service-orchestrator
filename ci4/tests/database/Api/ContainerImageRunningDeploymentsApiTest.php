<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\ContainerImage;
use App\Entities\Deployment;
use App\Fixtures;

/**
 * Which deployments run each image, by the same definition of running as the scanner.
 */
class ContainerImageRunningDeploymentsApiTest extends ControllerTestCase {

    public function testTheListHasTheDeploymentsRunningEachImage(): void {
        $api = Fixtures::containerImage(['name' => 'api']);
        $web = Fixtures::containerImage(['name' => 'web']);
        $idle = Fixtures::containerImage(['name' => 'idle']);
        $this->aDeployment($api);
        $this->aDeployment($api, ['version' => '2']);
        $this->aDeployment($web);

        $counts = [];
        foreach ($this->decode($this->signedIn()->get('container_images'))['resources'] as $image) {
            $counts[$image['name']] = count($image['running_deployment_ids']);
        }

        $this->assertSame(['api' => 2, 'web' => 1, 'idle' => 0], array_intersect_key($counts, array_flip(['api', 'web', 'idle'])));
    }

    public function testOneImageCarriesItsDeploymentsToo(): void {
        $image = Fixtures::containerImage();
        $deployment = $this->aDeployment($image);

        $this->assertSame([(int) $deployment->id], $this->decode($this->signedIn()->get("container_images/{$image->id}"))['resource']['running_deployment_ids']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deploymentsThatAreNotRunning')]
    public function testWhatDoesNotRunIsLeftOut(array $deployment, array $workspace): void {
        $image = Fixtures::containerImage();
        $this->aDeployment($image, $deployment, $workspace);

        $this->assertSame([], $this->decode($this->signedIn()->get("container_images/{$image->id}"))['resource']['running_deployment_ids']);
    }

    public static function deploymentsThatAreNotRunning(): array {
        return [
            'a draft' => [['status' => \DeploymentStatusTypes::Draft], []],
            'terminated' => [['status' => \DeploymentStatusTypes::Inactive], []],
            'without a version' => [['version' => ''], []],
            'in a paused workspace' => [[], ['status' => \WorkspaceStatusTypes::Paused, 'is_paused' => true]],
            'in a terminated workspace' => [[], ['status' => \WorkspaceStatusTypes::Inactive]],
        ];
    }

    private function aDeployment(ContainerImage $image, array $deployment = [], array $workspace = []): Deployment {
        $spec = Fixtures::deploymentSpecification(['container_image_id' => $image->id]);
        $workspace = Fixtures::workspace(array_merge(['status' => \WorkspaceStatusTypes::Synced], $workspace));
        return Fixtures::deployment(array_merge([
            'deployment_specification_id' => $spec->id,
            'workspace_id' => $workspace->id,
            'status' => \DeploymentStatusTypes::Synced,
            'version' => '1',
        ], $deployment));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
