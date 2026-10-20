<?php namespace App\Tests\Database\Kubernetes;

use App\Entities\Deployment;
use App\Fixtures;
use App\Libraries\Crypt;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\Kubernetes\ContainerEnvironment;
use App\Libraries\Kubernetes\GeneratedSecrets;
use App\Libraries\Kubernetes\WorkloadSecret;
use App\ManifestTestCase;
use RenokiCo\PhpK8s\Instances\Container;

/**
 * Secrets kso makes itself - `${secret.<name>}` for a deployment, `${workspace.secret.<name>}` for
 * its workspace - and where they go: the same value in every container that names it, into the
 * workload's Secret and never into the manifest, made once and kept until rotated.
 */
class GeneratedSecretsTest extends ManifestTestCase {

    /**
     * The point of it: the app signs with the key, the push server beside it checks with it.
     */
    public function testTheSameNameIsTheSameValueInTheAppAndItsSidecarAndNotInAnotherDeployment(): void {
        $deployment = $this->aDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'CENTRIFUGO_TOKEN_HMAC_SECRET', 'value' => '${secret.centrifugo_token}']);
        $sidecar = Fixtures::initContainer(['name' => 'push', 'container_image_id' => Fixtures::containerImage()->id, 'is_sidecar' => true]);
        Fixtures::initContainerEnvironmentVariable(['init_container_id' => $sidecar->id, 'name' => 'CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY', 'value' => '${secret.centrifugo_token}']);
        $sidecar->container_image->find();
        $secret = WorkloadSecret::For('api', 'deployment');

        $app = ContainerEnvironment::ofDeployment($deployment)->toArray()['CENTRIFUGO_TOKEN_HMAC_SECRET'];
        $sidecar->toKubernetesResource($deployment, $secret);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $app);
        $this->assertSame($app, $secret->data()['push.CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY']);
        $this->assertNotSame($app, GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $this->aDeployment()->id, 'centrifugo_token'));
    }

    public function testAWorkspacesSecretIsSharedByItsDeploymentsAndNotByAnotherWorkspaces(): void {
        $workspace = Fixtures::workspace();
        $one = $this->aDeployment(['workspace_id' => $workspace->id]);
        $two = $this->aDeployment(['workspace_id' => $workspace->id]);
        $elsewhere = $this->aDeployment(['workspace_id' => Fixtures::workspace(['namespace' => 'elsewhere'])->id]);

        $value = fn(Deployment $deployment) => GeneratedSecrets::Fill('${workspace.secret.signing}', $deployment);

        $this->assertSame($value($one), $value($two));
        $this->assertNotSame($value($one), $value($elsewhere));
    }

    /**
     * Kept from one deploy to the next, changed by a rotation - and the pod template changes with
     * it, through the Secret's checksum, so the pods roll at the next deploy.
     */
    public function testTheValueIsKeptUntilItIsRotatedAndARotationRollsThePods(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'STORAGE_URL_SECRET', 'value' => '${secret.storage_url}']);
        $checksum = fn() => $this->manifest(DeploymentStep::class, $deployment)['spec']['template']['metadata']['annotations'][WorkloadSecret::ChecksumAnnotation];
        $value = fn() => GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'storage_url');

        $first = $checksum();
        $kept = $value();
        $this->assertSame($first, $checksum(), 'a second deploy');
        $this->assertSame($kept, $value());

        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, (int) $deployment->id, 'storage_url');

        $this->assertNotSame($kept, $value());
        $this->assertNotSame($first, $checksum());
    }

    /**
     * Secret without the mark, in the manifest as a reference only - and stored encrypted.
     */
    public function testTheValueIsAReferenceInTheManifestAndEncryptedInTheDatabase(): void {
        $deployment = Fixtures::deployableDeployment();
        Fixtures::specificationEnvironmentVariable(['deployment_specification_id' => $deployment->deployment_specification_id, 'name' => 'CENTRIFUGO_HTTP_API_KEY', 'value' => '${secret.centrifugo_api}', 'is_secret' => false]);

        $manifest = $this->manifest(DeploymentStep::class, $deployment);
        $value = GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'centrifugo_api');

        $env = array_column($manifest['spec']['template']['spec']['containers'][0]['env'], null, 'name');
        $this->assertArrayHasKey('secretKeyRef', $env['CENTRIFUGO_HTTP_API_KEY']['valueFrom']);
        $this->assertStringNotContainsString($value, json_encode($manifest));
        $stored = db_connect()->table('deployment_secrets')->where('deployment_id', $deployment->id)->get()->getRowArray()['value'];
        $this->assertTrue(Crypt::IsEncrypted($stored));
        $this->assertStringNotContainsString($value, $stored);
    }

    /**
     * The deployment's own variables get theirs too - a workspace template writes there.
     */
    public function testTheDeploymentsOwnVariablesGetTheirSecrets(): void {
        $deployment = $this->aDeployment();
        Fixtures::deploymentEnvironmentVariable(['deployment_id' => $deployment->id, 'name' => 'KEY', 'value' => '${secret.key}']);
        Fixtures::deploymentEnvironmentVariable(['deployment_id' => $deployment->id, 'name' => 'NS', 'value' => '${namespace}']);

        $environment = ContainerEnvironment::ofDeployment($deployment);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $environment->toArray()['KEY']);
        $this->assertTrue($environment->isSecret('KEY'));
        $this->assertSame((string) $deployment->namespace, $environment->toArray()['NS']);
    }

    /**
     * Two deploys asking at once: the second insert finds the first one's row and changes
     * nothing, and both read that row.
     */
    public function testARowThatIsAlreadyThereIsTheOneUsed(): void {
        $deployment = $this->aDeployment();
        db_connect()->table('deployment_secrets')->insert([
            'deployment_id' => $deployment->id,
            'name' => 'token',
            'value' => Crypt::Encrypt('the-one-already-there'),
            'recipe' => 'randHex 64',
        ]);

        $this->assertSame('the-one-already-there', GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'token'));
        $this->assertSame(1, db_connect()->table('deployment_secrets')->where('deployment_id', $deployment->id)->countAllResults());
    }

    public function testAWorkspacesSecretOnADeploymentInNoWorkspaceSaysSo(): void {
        $deployment = $this->aDeployment(['workspace_id' => null, 'name' => 'loose']);

        $this->expectExceptionMessage('${workspace.secret.signing} belongs to a workspace, and loose is in none');

        GeneratedSecrets::Fill('${workspace.secret.signing}', $deployment);
    }

    /**
     * A deleted owner's secrets are kept, marked with who it was - its row is gone - and its
     * values can still be shown.
     */
    public function testTheSecretsAreKeptAfterTheirDeploymentAndWorkspaceAreDeleted(): void {
        $workspace = Fixtures::workspace(['name_readable' => 'Kunde A', 'namespace' => 'kunde-a']);
        $deployment = $this->aDeployment(['workspace_id' => $workspace->id, 'name' => 'api', 'namespace' => 'kunde-a']);
        $value = GeneratedSecrets::Fill('${secret.a}', $deployment);
        GeneratedSecrets::Fill('${workspace.secret.b}', $deployment);

        $deployment->delete();
        $workspace->delete();

        $this->assertSame([], GeneratedSecrets::Of(GeneratedSecrets::Deployment, (int) $deployment->id));
        $deleted = array_column(GeneratedSecrets::Deleted(), null, 'owner');
        $this->assertSame(['api', 'kunde-a', ['a']], [$deleted['deployment']['owner_name'], $deleted['deployment']['owner_namespace'], array_column($deleted['deployment']['secrets'], 'name')]);
        $this->assertSame(['Kunde A', ['b']], [$deleted['workspace']['owner_name'], array_column($deleted['workspace']['secrets'], 'name')]);
        $this->assertSame($value, GeneratedSecrets::Reveal(GeneratedSecrets::Deployment, (int) $deployment->id, 'a'));
    }

    public function testPurgingTakesADeletedOwnersSecretsAndVersionsAndNotALiveOnes(): void {
        $live = $this->aDeployment();
        $gone = $this->aDeployment();
        foreach ([$live, $gone] as $deployment) {
            GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'key');
            GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, (int) $deployment->id, 'key');
        }
        $gone->delete();

        $this->assertFalse(GeneratedSecrets::Purge(GeneratedSecrets::Deployment, (int) $live->id));
        $this->assertTrue(GeneratedSecrets::Purge(GeneratedSecrets::Deployment, (int) $gone->id));

        $this->assertSame([], GeneratedSecrets::Deleted());
        $this->assertSame(0, db_connect()->table('deployment_secret_versions')->where('deployment_id', $gone->id)->countAllResults());
        $this->assertSame(1, db_connect()->table('deployment_secret_versions')->where('deployment_id', $live->id)->countAllResults());
        $this->assertCount(1, GeneratedSecrets::Of(GeneratedSecrets::Deployment, (int) $live->id));
    }

    /**
     * The point of the versions: a key data was encrypted with is never gone. Rotated, the old
     * value is kept, can be shown, and is `.previous` - what an app reads with while it encrypts
     * again with the new one.
     */
    public function testARotatedValueIsKeptAsAVersionAndIsThePrevious(): void {
        $deployment = $this->aDeployment();
        $id = (int) $deployment->id;
        $this->assertSame('', GeneratedSecrets::Fill('${secret.ci4_key.previous}', $deployment), 'nothing before the first');

        $first = GeneratedSecrets::Fill('${secret.ci4_key}', $deployment);
        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, $id, 'ci4_key');
        $this->assertSame($first, GeneratedSecrets::Fill('${secret.ci4_key.previous}', $deployment), 'rotated, before the next deploy');
        $second = GeneratedSecrets::Fill('${secret.ci4_key}', $deployment);
        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, $id, 'ci4_key');
        $third = GeneratedSecrets::Fill('${secret.ci4_key}', $deployment);

        $this->assertSame($second, GeneratedSecrets::Fill('${secret.ci4_key.previous}', $deployment));
        $this->assertSame([$first, $second, $third], [
            GeneratedSecrets::Reveal(GeneratedSecrets::Deployment, $id, 'ci4_key', 1),
            GeneratedSecrets::Reveal(GeneratedSecrets::Deployment, $id, 'ci4_key', 2),
            GeneratedSecrets::Reveal(GeneratedSecrets::Deployment, $id, 'ci4_key', 3),
        ]);
        $listed = GeneratedSecrets::Of(GeneratedSecrets::Deployment, $id)[0];
        $this->assertSame(3, $listed['version']);
        $this->assertSame([2, 1], array_column($listed['versions'], 'version'));
        $this->assertStringNotContainsString($first, json_encode($listed));
    }

    /**
     * Rotated twice before a deploy, there was no value the second time - nothing to keep, and no
     * version counted for it.
     */
    public function testRotatingTwiceBeforeADeployKeepsOneVersion(): void {
        $deployment = $this->aDeployment();
        $id = (int) $deployment->id;
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, $id, 'key');

        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, $id, 'key');
        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, $id, 'key');
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, $id, 'key');

        $listed = GeneratedSecrets::Of(GeneratedSecrets::Deployment, $id)[0];
        $this->assertSame([2, [1]], [$listed['version'], array_column($listed['versions'], 'version')]);
    }

    /**
     * Restored, an earlier value is current again - as a new version, so the one it replaced is
     * kept too, and nothing is written over.
     */
    public function testRestoringMakesAnEarlierValueCurrentAsANewVersion(): void {
        $deployment = $this->aDeployment();
        $id = (int) $deployment->id;
        $first = GeneratedSecrets::Value(GeneratedSecrets::Deployment, $id, 'key');
        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, $id, 'key');
        $second = GeneratedSecrets::Value(GeneratedSecrets::Deployment, $id, 'key');

        GeneratedSecrets::Restore(GeneratedSecrets::Deployment, $id, 'key', 1);

        $this->assertSame($first, GeneratedSecrets::Value(GeneratedSecrets::Deployment, $id, 'key'));
        $this->assertSame($second, GeneratedSecrets::PreviousValue(GeneratedSecrets::Deployment, $id, 'key'));
        $listed = GeneratedSecrets::Of(GeneratedSecrets::Deployment, $id)[0];
        $this->assertSame([3, 'restored from version 1', false], [$listed['version'], $listed['origin'], $listed['pending']]);
        $this->assertSame([2, 1], array_column($listed['versions'], 'version'));
    }

    /**
     * Restored while it waits to be made anew: there is no value to keep, and the restored one
     * takes the version counted for the new one.
     */
    public function testRestoringARotatedSecretGivesItTheValueBeforeTheDeployMakesOne(): void {
        $deployment = $this->aDeployment();
        $id = (int) $deployment->id;
        $first = GeneratedSecrets::Value(GeneratedSecrets::Deployment, $id, 'key');
        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, $id, 'key');

        GeneratedSecrets::Restore(GeneratedSecrets::Deployment, $id, 'key', 1);

        $this->assertSame($first, GeneratedSecrets::Value(GeneratedSecrets::Deployment, $id, 'key'));
        $this->assertSame([2, [1]], [GeneratedSecrets::Of(GeneratedSecrets::Deployment, $id)[0]['version'], array_column(GeneratedSecrets::Of(GeneratedSecrets::Deployment, $id)[0]['versions'], 'version')]);
    }

    public function testRestoringAVersionThatIsNotThereSaysSo(): void {
        $deployment = $this->aDeployment();
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'key');

        $this->expectExceptionMessage('key has no version 7 to restore');

        GeneratedSecrets::Restore(GeneratedSecrets::Deployment, (int) $deployment->id, 'key', 7);
    }

    /**
     * A value kept elsewhere put back by hand: current, encrypted, with the recipe it was made
     * with - also when it was rotated and had none - and the one it replaced is kept.
     */
    public function testAValueSetByHandIsCurrentAndKeepsTheRecipe(): void {
        $workspace = Fixtures::workspace();
        $id = (int) $workspace->id;
        $deployment = $this->aDeployment(['workspace_id' => $id]);
        $made = GeneratedSecrets::Fill('${workspace.secret.key | randAlphaNum 32}', $deployment);
        GeneratedSecrets::Rotate(GeneratedSecrets::Workspace, $id, 'key');

        GeneratedSecrets::Set(GeneratedSecrets::Workspace, $id, 'key', 'from-the-backup');

        $this->assertSame('from-the-backup', GeneratedSecrets::Fill('${workspace.secret.key | randAlphaNum 32}', $deployment));
        $this->assertSame($made, GeneratedSecrets::Fill('${workspace.secret.key.previous}', $deployment));
        $listed = GeneratedSecrets::Of(GeneratedSecrets::Workspace, $id)[0];
        $this->assertSame(['randAlphaNum 32', 'set by hand'], [$listed['recipe'], $listed['origin']]);
        $stored = db_connect()->table('workspace_secrets')->where('workspace_id', $id)->get()->getRowArray()['value'];
        $this->assertTrue(Crypt::IsEncrypted($stored));
    }

    public function testNoValueIsNotAValue(): void {
        $deployment = $this->aDeployment();
        GeneratedSecrets::Value(GeneratedSecrets::Deployment, (int) $deployment->id, 'key');

        $this->expectExceptionMessage('a value is needed');

        GeneratedSecrets::Set(GeneratedSecrets::Deployment, (int) $deployment->id, 'key', '');
    }

    /**
     * Asking for the previous value makes nothing: not the secret, not a version.
     */
    public function testThePreviousOfASecretThatIsNotThereIsEmptyAndMakesNothing(): void {
        $deployment = $this->aDeployment();

        $this->assertSame('', GeneratedSecrets::Fill('${secret.nope.previous}', $deployment));
        $this->assertSame([], GeneratedSecrets::Of(GeneratedSecrets::Deployment, (int) $deployment->id));
    }

    /**
     * What it is made of is written after the name, as in a Helm chart.
     */
    public function testTheRecipeAfterTheNameIsWhatItIsMadeOf(): void {
        $deployment = $this->aDeployment();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', GeneratedSecrets::Fill('${secret.token | randAlphaNum 32}', $deployment));
        $this->assertSame('randAlphaNum 32', GeneratedSecrets::Of(GeneratedSecrets::Deployment, (int) $deployment->id)[0]['recipe']);
    }

    /**
     * One name, one recipe: written another way somewhere else, it is refused rather than given a
     * value of the wrong kind. Rotating is how the recipe changes - the next deploy makes it anew.
     */
    public function testAnotherRecipeForTheSameNameIsRefusedUntilItIsRotated(): void {
        $deployment = $this->aDeployment();
        GeneratedSecrets::Fill('${secret.token | randAlphaNum 32}', $deployment);

        try {
            GeneratedSecrets::Fill('${secret.token | randNumeric 6}', $deployment);
            $this->fail('another recipe for the same name was taken');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('another recipe than kso made it with (randAlphaNum 32)', $e->getMessage());
        }

        GeneratedSecrets::Rotate(GeneratedSecrets::Deployment, (int) $deployment->id, 'token');
        $this->assertTrue(GeneratedSecrets::Of(GeneratedSecrets::Deployment, (int) $deployment->id)[0]['pending']);

        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', GeneratedSecrets::Fill('${secret.token | randNumeric 6}', $deployment));
        $this->assertSame('randNumeric 6', GeneratedSecrets::Of(GeneratedSecrets::Deployment, (int) $deployment->id)[0]['recipe']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('names')]
    public function testANameIsCheckedWhenTheVariablesAreSaved(string $value, bool $valid): void {
        $reason = GeneratedSecrets::ReasonVariablesAreInvalid([(object) ['name' => 'X', 'value' => $value]]);

        $valid ? $this->assertNull($reason) : $this->assertNotNull($reason);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function names(): array {
        return [
            'a name' => ['${secret.api_key2}', true],
            'a workspace name' => ['${workspace.secret.signing}', true],
            'in a longer value' => ['https://x?sig=${secret.sig}', true],
            'no placeholder' => ['plain', true],
            'capitals' => ['${secret.ApiKey}', false],
            'a dash' => ['${secret.api-key}', false],
            'empty' => ['${secret.}', false],
            'not closed' => ['${secret.api', false],
            'a recipe' => ['${secret.token | randAlphaNum 32}', true],
            'a recipe without spaces' => ['${secret.token|uuidv4}', true],
            'an unknown recipe' => ['${secret.token | randomString 32}', false],
            'a recipe of nothing' => ['${secret.token | randHex 0}', false],
            'the previous' => ['${secret.ci4_key.previous}', true],
            'a workspace previous' => ['${workspace.secret.signing.previous}', true],
            'the previous with a recipe' => ['${secret.ci4_key.previous | randHex 64}', false],
            'another suffix' => ['${secret.ci4_key.next}', false],
        ];
    }

    // <editor-fold desc="Fixtures">

    /**
     * @param array<string, mixed> $overrides
     */
    private function aDeployment(array $overrides = []): Deployment {
        return Fixtures::deployment(array_merge([
            'deployment_specification_id' => Fixtures::deploymentSpecification()->id,
            'version' => '1.0.0',
        ], $overrides));
    }

    // </editor-fold>

}
