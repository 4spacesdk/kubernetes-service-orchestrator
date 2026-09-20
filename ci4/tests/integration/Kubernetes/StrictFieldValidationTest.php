<?php namespace App\Tests\Integration\Kubernetes;

use App\ClusterTestCase;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sConfigMap;

/**
 * A field kso spells wrong is refused, rather than dropped without a word.
 *
 * Kubernetes removes a field it does not recognise and answers `201 Created` with a warning
 * header - not an error - unless the client asks for `fieldValidation`. `kubectl apply`
 * asks; php-k8s does not, so kso did not either. A field renamed in a newer api version, or
 * put one level too deep, was silently left out of the resource that got created, and the
 * missing behaviour turned up later as an operational fault somewhere else entirely.
 *
 * This is the guard on the flag itself. It is worth having on its own, because the flag is
 * one entry in one array and nothing else in the suite would notice it going missing: every
 * other test asserts what a manifest contains, which is true whether the unknown field was
 * refused or quietly removed.
 */
class StrictFieldValidationTest extends ClusterTestCase {

    /**
     * The mutation that started this: a field renamed by one character. Against a real api
     * server, with a real resource - a ConfigMap, because it is the cheapest kind that has
     * a field to misspell and needs no controller to do anything about it.
     */
    public function testAResourceWithAFieldTheApiServerDoesNotKnowIsRefused(): void {
        $resource = (new K8sConfigMap((new KubeAuth())->authenticate()))
            ->setName('kso-strict-probe')
            ->setNamespace($this->testNamespace)
            ->setAttribute('dataX', ['spelled' => 'wrong']);

        try {
            KubeHelper::Apply($resource);
            $this->fail('the api server accepted an unknown field - fieldValidation is not being sent');
        } catch (KubernetesAPIException $e) {
            $this->assertStringContainsString('unknown field', KubeHelper::PrintException($e));
            $this->assertStringContainsString('dataX', KubeHelper::PrintException($e));
        }
    }

    /**
     * And the same resource without the mistake still applies, so the test above is about
     * the unknown field rather than about anything else being wrong with the manifest.
     */
    public function testTheSameResourceWithoutTheMistakeIsApplied(): void {
        (new NamespaceStep())->startDeployCommand($this->deploymentInTheTestNamespace());

        $resource = (new K8sConfigMap((new KubeAuth())->authenticate()))
            ->setName('kso-strict-probe')
            ->setNamespace($this->testNamespace)
            ->setAttribute('data', ['spelled' => 'right']);

        KubeHelper::Apply($resource);

        $applied = $this->cluster()->getConfigmapByName('kso-strict-probe', $this->testNamespace);
        $this->assertSame(['spelled' => 'right'], $applied->getAttribute('data'));
    }

}
