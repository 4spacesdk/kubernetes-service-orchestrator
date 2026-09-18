<?php namespace App;

use App\Entities\Deployment;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;

/**
 * Base for tests that check what a deployment step would send to the cluster.
 *
 * Every step builds its resource in `getResource()` or `getResources()`, and passing
 * `$auth = false` builds it in memory without contacting anything. Those methods are
 * protected, so the manifest is reached through a small subclass per step - this class
 * holds the reflection and the decoding so each test file does not repeat them.
 *
 * A step whose manifest is worth testing needs its builder changed from `private` to
 * `protected`. That is the whole cost of making one testable.
 */
abstract class ManifestTestCase extends DatabaseTestCase {

    /**
     * The manifest a step would apply, decoded.
     *
     * @param class-string<BaseDeploymentStep> $step
     * @return array<string, mixed>
     */
    protected function manifest(string $step, Deployment $deployment): array {
        $manifests = $this->manifests($step, $deployment);

        $this->assertCount(
            1,
            $manifests,
            sprintf('%s built %d manifests, expected exactly one', $step, count($manifests))
        );

        return $manifests[0];
    }

    /**
     * The manifests a step would apply. Steps that generate one resource return a single
     * entry; steps like the HTTPRoute one return several.
     *
     * @param class-string<BaseDeploymentStep> $step
     * @return array<array<string, mixed>>
     */
    protected function manifests(string $step, Deployment $deployment): array {
        $instance = new $step();

        $method = method_exists($instance, 'getResources') ? 'getResources' : 'getResource';
        $reflection = new \ReflectionMethod($instance, $method);

        $built = $reflection->invoke($instance, $deployment, false);
        $resources = is_array($built) ? $built : [$built];

        return array_map(
            static fn ($resource) => json_decode($resource->toJson(), true),
            $resources
        );
    }

    /**
     * Assert that a step decides not to generate anything for this deployment. Steps say
     * that by building a resource with no spec rather than by building nothing.
     *
     * @param class-string<BaseDeploymentStep> $step
     */
    protected function assertNoSpecGenerated(string $step, Deployment $deployment): void {
        $this->assertArrayNotHasKey('spec', $this->manifest($step, $deployment));
    }

}
