<?php namespace App\Tests\Unit\GoogleCloud;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\GoogleCloud\GcrSubscription;
use App\Libraries\Kubernetes\KubeHelper;

/**
 * The name this installation subscribes to a registry's push topic under.
 *
 * It is built in one place now, but it used to be built in two - where the subscription is
 * created and where it is read - and the two agreeing is the only thing that makes auto
 * updates work. If they drift, kso subscribes to one queue and reads an empty one, with no
 * error anywhere.
 *
 * So the shape is pinned here rather than compared against itself. A test that asserts
 * `name() === name()` would pass whatever the name became.
 */
class GcrSubscriptionNameTest extends CIUnitTestCase {

    public function testTheNameCarriesTheProjectThePodAndTheNamespace(): void {
        $name = GcrSubscription::name();

        $this->assertStringContainsString(
            strtolower((string) getenv('PROJECT_NAME')),
            $name,
            'the project, so two installations do not share a subscription'
        );
        $this->assertStringContainsString('.kso-' . KubeHelper::GetMyHostname(), $name, 'the pod');
        $this->assertStringContainsString('.' . KubeHelper::GetMyNamespace(), $name, 'the namespace');
    }

    /**
     * A subscription name cannot carry spaces, and a project name can.
     */
    public function testSpacesInTheProjectNameBecomeUnderscores(): void {
        $original = getenv('PROJECT_NAME');
        putenv('PROJECT_NAME=Acme Industries');

        try {
            $this->assertStringStartsWith('acme_industries.', GcrSubscription::name());
        } finally {
            putenv('PROJECT_NAME=' . $original);
        }
    }

    public function testTheTopicIsTheOneArtifactRegistryPublishesTo(): void {
        $this->assertSame('gcr', GcrSubscription::TOPIC);
    }

}
