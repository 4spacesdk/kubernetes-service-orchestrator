<?php namespace App\Tests\Database\DeploymentSteps;

use App\Entities\ContainerRegistry;
use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\KServiceStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\RegistryPullSecretStep;
use App\ManifestTestCase;

/**
 * Pull secrets made by kso from a registry connection's own pull login (INT-1d).
 *
 * Two halves that have to agree by name: the step makes `kso-registry-{id}` in the
 * namespace, and every pod whose images come from that registry names it. A pod that
 * names a secret nobody made does not start - ImagePullBackOff, long after the deploy
 * went green.
 */
class RegistryPullSecretStepTest extends ManifestTestCase {

    // <editor-fold desc="The secret">

    public function testSecretHoldsTheRegistrysPullLoginForItsHost(): void {
        $registry = $this->registryWithPullLogin();
        $deployment = $this->deploymentFrom($registry);

        $manifest = $this->manifest(RegistryPullSecretStep::class, $deployment);

        $this->assertSame("kso-registry-{$registry->id}", $manifest['metadata']['name']);
        $this->assertSame($deployment->namespace, $manifest['metadata']['namespace']);
        $this->assertSame('kubernetes.io/dockerconfigjson', $manifest['type']);
        $config = json_decode(base64_decode($manifest['data']['.dockerconfigjson']), true);
        $this->assertSame([
            'registry.example.org' => [
                'username' => 'robot$pull',
                'password' => 'pull-token',
                'auth' => base64_encode('robot$pull:pull-token'),
            ],
        ], $config['auths']);
    }

    /**
     * The login kso reads tags with may write webhooks, so it is never the one pods get.
     */
    public function testNoPullLoginMeansNoSecretAndNoStep(): void {
        $registry = Fixtures::containerRegistry();
        $deployment = $this->deploymentFrom($registry);

        $this->assertSame([], $this->manifests(RegistryPullSecretStep::class, $deployment));
        $this->assertNotContains('RegistryPullSecretStep', $this->steps($deployment));
    }

    public function testStepRunsBeforeTheWorkload(): void {
        $steps = $this->steps($this->deploymentFrom($this->registryWithPullLogin()));

        $this->assertLessThan(
            array_search('DeploymentStep', $steps),
            array_search('RegistryPullSecretStep', $steps)
        );
    }

    /**
     * A rollout is what makes pods name the secret, so every rollout makes the secret too.
     */
    public function testRunsOnEveryTriggerThatRollsOutAPod(): void {
        $triggers = (new RegistryPullSecretStep())->getTriggers();

        foreach ([DeploymentStep::class, KServiceStep::class, MigrationJobStep::class] as $step) {
            foreach ((new $step())->getTriggers() as $trigger) {
                $this->assertContains($trigger, $triggers, "{$step} rolls out on {$trigger}");
            }
        }
    }

    public function testOneSecretPerRegistryHoweverManyImagesComeFromIt(): void {
        $registry = $this->registryWithPullLogin();
        $deployment = $this->deploymentFrom($registry);
        $this->addInitContainer($deployment, Fixtures::containerImage(['container_registry_id' => $registry->id]));

        $this->assertCount(1, $this->manifests(RegistryPullSecretStep::class, $deployment));
    }

    /**
     * A cron job's image counts only while cron jobs are turned on - otherwise nothing
     * would pull it.
     */
    public function testCronJobImagesCountOnlyWithCronJobsOn(): void {
        $registry = $this->registryWithPullLogin();
        $deployment = Fixtures::deployableDeployment();
        $cronJob = Fixtures::cronJob([
            'container_image_id' => Fixtures::containerImage(['container_registry_id' => $registry->id])->id,
        ]);
        Fixtures::deploymentCronJob(['deployment_id' => $deployment->id, 'k8s_cron_job_id' => $cronJob->id]);

        $this->assertSame([], $this->manifests(RegistryPullSecretStep::class, $deployment));

        $spec = $deployment->findDeploymentSpecification();
        $spec->enable_cronjob = true;
        $spec->save();

        $this->assertCount(1, $this->manifests(RegistryPullSecretStep::class, $this->reload($deployment)));
    }

    // </editor-fold>

    // <editor-fold desc="The pods that name it">

    /**
     * The secret named on the image stays next to kso's, so moving an image over does not
     * need a moment where its pods have neither.
     */
    public function testPodNamesTheManagedSecretAndTheImagesOwn(): void {
        $registry = $this->registryWithPullLogin();
        $deployment = $this->deploymentFrom($registry, ['pull_secret' => 'registry-credentials']);

        $this->assertSame(
            [['name' => "kso-registry-{$registry->id}"], ['name' => 'registry-credentials']],
            $this->podSpec($deployment)['imagePullSecrets']
        );
    }

    /**
     * A pod has one list of pull secrets for all its containers. An init container from
     * another registry used to be left out of it, and so could not be pulled.
     */
    public function testInitContainerFromAnotherRegistryAddsItsSecret(): void {
        $deployment = Fixtures::deployableDeployment();
        $other = $this->registryWithPullLogin();
        $this->addInitContainer($deployment, Fixtures::containerImage([
            'container_registry_id' => $other->id,
            'pull_secret' => '',
        ]));

        $this->assertSame(
            [['name' => "kso-registry-{$other->id}"]],
            $this->podSpec($deployment)['imagePullSecrets']
        );
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    private function registryWithPullLogin(): ContainerRegistry {
        return Fixtures::containerRegistry([
            'pull_username' => 'robot$pull',
            'pull_password' => 'pull-token',
        ]);
    }

    /**
     * @param array<string, mixed> $image
     */
    private function deploymentFrom(ContainerRegistry $registry, array $image = []): Deployment {
        return Fixtures::deployableDeployment([], [], array_merge([
            'container_registry_id' => $registry->id,
            'pull_secret' => '',
        ], $image));
    }

    private function addInitContainer(Deployment $deployment, \App\Entities\ContainerImage $image): void {
        $initContainer = Fixtures::initContainer(['container_image_id' => $image->id]);
        Fixtures::specificationInitContainer([
            'deployment_specification_id' => $deployment->deployment_specification_id,
            'init_container_id' => $initContainer->id,
        ]);
    }

    private function reload(Deployment $deployment): Deployment {
        $fresh = new Deployment();
        $fresh->find($deployment->id);
        return $fresh;
    }

    /**
     * @return array<string, mixed>
     */
    private function podSpec(Deployment $deployment): array {
        return $this->manifest(DeploymentStep::class, $deployment)['spec']['template']['spec'];
    }

    /**
     * @return string[]
     */
    private function steps(Deployment $deployment): array {
        return array_map(
            static fn ($step) => (new \ReflectionClass($step))->getShortName(),
            $deployment->findDeploymentSpecification()->getDeploymentSteps($deployment)
        );
    }

    // </editor-fold>

}
