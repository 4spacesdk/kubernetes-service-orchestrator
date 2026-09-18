<?php namespace App\Tests\Integration\Api;

use App\ClusterControllerTestCase;
use App\Entities\Gateway;
use App\Fixtures;
use App\Libraries\DeploymentSteps\NamespaceStep;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Gateways endpoints - the buttons on a gateway's page.
 *
 * Every one of them is a thin shell around `GatewayStep`: find the row, drive the step,
 * and turn whatever the cluster said into a response. The step itself is covered by
 * `GatewayStepTest`; what is covered here is the shell, and it is the shell that decides
 * what an operator sees when the cluster says no.
 *
 * These are the first tests that need both halves at once - a real request *and* a real
 * cluster - which is what `ClusterControllerTestCase` is for.
 */
class GatewaysApiTest extends ClusterControllerTestCase {

    public function testDeployingPutsTheGatewayOnTheCluster(): void {
        $gateway = $this->gatewayInTheTestNamespace();

        $body = $this->decode($this->signedIn()->put("gateways/{$gateway->id}/deploy"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('found', $this->statusOf($gateway));
    }

    public function testTerminatingTakesItAwayAgain(): void {
        $gateway = $this->gatewayInTheTestNamespace();
        $this->signedIn()->put("gateways/{$gateway->id}/deploy");

        $this->signedIn()->put("gateways/{$gateway->id}/terminate");

        $this->eventually(fn () => $this->statusOf($gateway) === 'not-found');
    }

    public function testThePreviewCarriesWhatWouldBeSent(): void {
        $gateway = $this->gatewayInTheTestNamespace();

        $body = $this->decode($this->signedIn()->get("gateways/{$gateway->id}/preview"));

        $preview = json_decode($body['resource']['value'], true);
        $this->assertSame([], $preview['remote'], 'nothing applied yet');
        $this->assertSame($gateway->name, json_decode($preview['local'][0], true)['metadata']['name']);
    }

    /**
     * The status panel. Nothing runs a gateway controller in the test cluster, so what
     * comes back is what the api server writes by itself - which is also what an operator
     * sees when the controller is missing or misconfigured.
     */
    public function testTheKubernetesStatusIsWhateverTheApiServerWrote(): void {
        $gateway = $this->gatewayInTheTestNamespace();
        $this->signedIn()->put("gateways/{$gateway->id}/deploy");

        $body = $this->decode($this->signedIn()->get("gateways/{$gateway->id}/kubernetes-status"));

        $conditions = $body['resource']['value']['conditions'];
        $this->assertSame(['Accepted', 'Programmed'], array_column($conditions, 'type'));
    }

    public function testThereAreNoEventsForAGatewayNothingHasHappenedTo(): void {
        $gateway = $this->gatewayInTheTestNamespace();
        $this->signedIn()->put("gateways/{$gateway->id}/deploy");

        $body = $this->decode($this->signedIn()->get("gateways/{$gateway->id}/kubernetes-events"));

        $this->assertSame([], $body['resource']['value']);
    }

    /**
     * Addresses are the one endpoint here that writes to the database rather than to the
     * cluster. The list replaces what was there.
     */
    public function testAddressesReplaceWhatWasThere(): void {
        $gateway = $this->gatewayInTheTestNamespace();
        Fixtures::gatewayAddress(['gateway_id' => $gateway->id, 'value' => '10.0.0.1']);

        $this->withBodyFormat('json')->signedIn()->put("gateways/{$gateway->id}/gateway-addresses", [
            'values' => [['type' => 'IPAddress', 'value' => '10.0.0.9']],
        ]);

        // `deletion_id IS NULL` is not optional: rows are soft deleted, so a raw query
        // without it sees everything the gateway has ever had.
        $rows = db_connect()->table('gateway_addresses')
            ->where('gateway_id', $gateway->id)
            ->where('deletion_id', null)
            ->get()->getResultArray();
        $this->assertSame(['10.0.0.9'], array_column($rows, 'value'));
    }

    /**
     * The gateway endpoints answer an unknown id with a failure rather than a cheerful
     * empty resource - which is what FEAT-9 asks for everywhere else. **The guard was
     * written and did not work**: `find()` returns an empty entity, never null, so
     * `!$gateway` was always false and all six endpoints fatalled instead. See FEAT-20.
     */
    #[DataProvider('theEndpointsThatTakeAnId')]
    public function testAnUnknownGatewayIsRefused(string $method, string $path): void {
        $body = $this->decode($this->signedIn()->$method("gateways/999999/{$path}"));

        $this->assertNotSame('OK', $body['status']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theEndpointsThatTakeAnId(): array {
        return [
            'deploy' => ['put', 'deploy'],
            'terminate' => ['put', 'terminate'],
            'preview' => ['get', 'preview'],
            'status' => ['get', 'status'],
            'kubernetes events' => ['get', 'kubernetes-events'],
            'kubernetes status' => ['get', 'kubernetes-status'],
        ];
    }

    /**
     * What an operator sees when the cluster refuses. The namespace does not exist, so
     * applying the gateway fails - and the endpoint has to turn that into a message rather
     * than a stack trace.
     */
    public function testAClusterRefusalComesBackAsAMessage(): void {
        $gateway = $this->gatewayInTheTestNamespace(['namespace' => $this->testNamespace . '-not-a-namespace']);

        $body = $this->decode($this->signedIn()->put("gateways/{$gateway->id}/deploy"));

        $this->assertNotSame('OK', $body['status']);
    }

    // <editor-fold desc="Fixtures">

    private function statusOf(Gateway $gateway): string {
        return $this->decode($this->signedIn()->get("gateways/{$gateway->id}/status"))['resource']['value'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function gatewayInTheTestNamespace(array $overrides = []): Gateway {
        $gateway = Fixtures::gateway(array_merge([
            'name' => 'kso-gateway',
            'namespace' => $this->testNamespace,
        ], $overrides));
        Fixtures::domain(['gateway_id' => $gateway->id, 'certificate_namespace' => $this->testNamespace]);

        (new NamespaceStep())->startDeployCommand($this->deploymentInTheTestNamespace());

        return $gateway;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
