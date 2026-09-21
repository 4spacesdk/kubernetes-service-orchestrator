<?php namespace App\Tests\Unit\Kubernetes;

use App\Entities\Deployment;
use App\Libraries\Kubernetes\SecretPreview;
use App\Libraries\Kubernetes\WorkloadSecret;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * No secret value reaches the preview - not from the workload as it is in the cluster, not
 * from its Secret - and the diff still shows that one will change.
 */
class SecretPreviewTest extends CIUnitTestCase {

    public function testWithoutSecretsThePreviewIsTheWorkloadAlone(): void {
        $local = $this->workload([['name' => 'LOG_LEVEL', 'value' => 'debug']]);
        $remote = $this->workload([['name' => 'LOG_LEVEL', 'value' => 'info']]);

        $preview = SecretPreview::of(json_encode($local), $remote, $this->secret(), $this->deployment(), null);

        $this->assertSame(['local' => json_encode($local), 'remote' => json_encode($remote)], $preview);
    }

    /**
     * Deployed before the variable went into a Secret, the cluster has it in the clear.
     */
    public function testAValueTheClusterStillHasInThePodSpecIsHidden(): void {
        $secret = $this->secret(['API_TOKEN' => 'token-value']);
        $local = $this->workload([$this->fromSecret('API_TOKEN'), ['name' => 'LOG_LEVEL', 'value' => 'debug']]);
        $remote = $this->workload([['name' => 'API_TOKEN', 'value' => 'token-value'], ['name' => 'LOG_LEVEL', 'value' => 'debug']]);

        $preview = SecretPreview::of(json_encode($local), $remote, $secret, $this->deployment(), null);

        $remoteWorkload = json_decode($preview['remote'][0], true);
        $this->assertSame(
            [['name' => 'API_TOKEN', 'value' => SecretPreview::Hidden], ['name' => 'LOG_LEVEL', 'value' => 'debug']],
            $remoteWorkload['spec']['template']['spec']['containers'][0]['env']
        );
        $this->assertStringNotContainsString('token-value', json_encode($preview));
    }

    /**
     * In an init container, and in a cron job's template two levels further down, too.
     */
    public function testItIsHiddenWhereverThePodTemplateIs(): void {
        $local = ['spec' => ['jobTemplate' => ['spec' => ['template' => ['spec' => [
            'containers' => [['name' => 'job', 'env' => [$this->fromSecret('API_TOKEN')]]],
        ]]]]]];
        $remote = ['spec' => ['jobTemplate' => ['spec' => ['template' => ['spec' => [
            'initContainers' => [['name' => 'init', 'env' => [['name' => 'API_TOKEN', 'value' => 'token-value']]]],
            'containers' => [['name' => 'job', 'env' => [['name' => 'API_TOKEN', 'value' => 'token-value']]]],
        ]]]]]];

        $this->assertStringNotContainsString('token-value', json_encode(SecretPreview::hideValues($remote, $local)));
    }

    public function testTheSecretIsShownBesideTheWorkloadWithItsValuesHidden(): void {
        $secret = $this->secret(['API_TOKEN' => 'token-value', 'OTHER' => 'unchanged']);
        $local = $this->workload([$this->fromSecret('API_TOKEN'), $this->fromSecret('OTHER')]);

        $preview = SecretPreview::of(json_encode($local), $local, $secret, $this->deployment(), [
            'api.API_TOKEN' => 'old-value',
            'api.OTHER' => 'unchanged',
        ]);

        $this->assertCount(2, $preview['local']);
        $this->assertCount(2, $preview['remote']);
        $this->assertSame(
            ['api.API_TOKEN' => SecretPreview::Changed, 'api.OTHER' => SecretPreview::Hidden],
            json_decode($preview['local'][1], true)['data']
        );
        $this->assertSame(
            ['api.API_TOKEN' => SecretPreview::Hidden, 'api.OTHER' => SecretPreview::Hidden],
            json_decode($preview['remote'][1], true)['data']
        );
        $this->assertSame('api-deployment-env', json_decode($preview['local'][1], true)['metadata']['name']);
        $this->assertStringNotContainsString('token-value', json_encode($preview));
        $this->assertStringNotContainsString('old-value', json_encode($preview));
        $this->assertStringNotContainsString('unchanged"', json_encode($preview));
    }

    /**
     * A Secret that is not there yet: every value is new.
     */
    public function testANewSecretHasEveryValueChanged(): void {
        $secret = $this->secret(['API_TOKEN' => 'token-value']);
        $local = $this->workload([$this->fromSecret('API_TOKEN')]);

        $preview = SecretPreview::of(json_encode($local), null, $secret, $this->deployment(), null);

        $this->assertSame([], $preview['remote']);
        $this->assertSame(['api.API_TOKEN' => SecretPreview::Changed], json_decode($preview['local'][1], true)['data']);
    }

    /**
     * No secrets any more, and the Secret from before still there - it is shown, so the
     * diff says it goes.
     */
    public function testASecretNoLongerNeededIsShownOnTheRemoteSide(): void {
        $local = $this->workload([['name' => 'LOG_LEVEL', 'value' => 'debug']]);

        $preview = SecretPreview::of(json_encode($local), $local, $this->secret(), $this->deployment(), ['api.API_TOKEN' => 'token-value']);

        $this->assertCount(1, $preview['local']);
        $this->assertCount(2, $preview['remote']);
        $this->assertStringNotContainsString('token-value', json_encode($preview));
    }

    // <editor-fold desc="Helpers">

    private function deployment(): Deployment {
        $deployment = new Deployment();
        $deployment->name = 'api';
        $deployment->namespace = 'tenant';

        return $deployment;
    }

    /**
     * @param array<string, string> $values
     */
    private function secret(array $values = []): WorkloadSecret {
        $secret = WorkloadSecret::For('api', 'deployment');
        foreach ($values as $name => $value) {
            $secret->add('api', $name, $value);
        }

        return $secret;
    }

    private function fromSecret(string $name): array {
        return ['name' => $name, 'valueFrom' => ['secretKeyRef' => ['name' => 'api-deployment-env', 'key' => "api.{$name}"]]];
    }

    private function workload(array $env): array {
        return ['kind' => 'Deployment', 'spec' => ['template' => ['spec' => ['containers' => [['name' => 'api', 'env' => $env]]]]]];
    }

    // </editor-fold>

}
