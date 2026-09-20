<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\Gateway;
use App\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the Gateways endpoints do when there is no cluster to talk to.
 *
 * The happy paths belong in `App\Tests\Integration\Api\GatewaysApiTest`, which drives a real
 * cluster. What is left is everything the controller decides *before* or *instead of* the
 * cluster call, and that is worth having in the database suite: it runs on every save, and a
 * cluster that cannot be reached is the normal failure in production, not an exotic one.
 *
 * `DatabaseTestCase` clears `KUBERNETES_AUTH` for every test outside the integration suite,
 * so `KubeAuth::authenticate()` throws on the first line of `GatewayStep` and each endpoint
 * is left holding an exception. What it does with it is the subject here - and it is not the
 * same thing in all six.
 */
class GatewaysWithoutAClusterTest extends ControllerTestCase {

    /**
     * Four of the six endpoints turn a failure into a message the operator can read. The
     * exception is caught, run through `KubeHelper::PrintException()` and returned as
     * `status: ERROR`, so the gateway page shows a reason instead of a stack trace.
     */
    #[DataProvider('theEndpointsThatAnswerWithAMessage')]
    public function testAnUnreachableClusterComesBackAsAMessage(string $method, string $path): void {
        $gateway = $this->gateway();

        $body = $this->decode($this->signedIn()->$method("gateways/{$gateway->id}/{$path}"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('missing KUBERNETES_AUTH', $body['error']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function theEndpointsThatAnswerWithAMessage(): array {
        return [
            'preview' => ['get', 'preview'],
            'deploy' => ['put', 'deploy'],
            'terminate' => ['put', 'terminate'],
        ];
    }

    /**
     * The status panel is the one endpoint that does not go through the controller's own
     * catch: `GatewayStep::getStatus()` swallows the exception itself and answers `error`.
     * So the page gets a word rather than a refusal, and the request is a success.
     */
    public function testTheStatusPanelSaysErrorRatherThanFailing(): void {
        $gateway = $this->gateway();

        $body = $this->decode($this->signedIn()->get("gateways/{$gateway->id}/status"));

        $this->assertSame('OK', $body['status']);
        $this->assertSame('error', $body['resource']['value']);
    }

    /**
     * The two Kubernetes panels used to be the odd ones out: no catch at all, so the
     * exception left the controller and the caller got whatever the framework made of it
     * instead of the `status: ERROR` its four siblings answer. All six agree now.
     */
    #[DataProvider('theKubernetesPanels')]
    public function testTheKubernetesPanelsAnswerWithAMessageToo(string $path): void {
        $gateway = $this->gateway();

        $body = $this->decode($this->signedIn()->get("gateways/{$gateway->id}/{$path}"));

        $this->assertSame('ERROR', $body['status']);
        $this->assertStringContainsString('missing KUBERNETES_AUTH', $body['error'] ?? '');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theKubernetesPanels(): array {
        return [
            'kubernetes events' => ['kubernetes-events'],
            'kubernetes status' => ['kubernetes-status'],
        ];
    }

    // <editor-fold desc="Addresses, which never touch the cluster">

    /**
     * An address list is written to the database and nowhere else, so the whole endpoint is
     * reachable without a cluster - including the refusal, which is the part the cluster
     * test cannot reach: one bad entry stops the request before anything is saved.
     *
     * `type` is a Gateway API enum extended with a vendor-prefixed form, and the pattern
     * accepts `Hostname`, `IPAddress`, `NamedAddress` or `<domain>/<name>`. A bare word is
     * none of those.
     */
    public function testAnAddressWithAnInvalidTypeIsRefused(): void {
        $gateway = $this->gateway();

        $body = $this->decode(
            $this->withBodyFormat('json')->signedIn()->put("gateways/{$gateway->id}/gateway-addresses", [
                'values' => [['type' => 'not a type', 'value' => '10.0.0.9']],
            ])
        );

        $this->assertSame('ERROR', $body['status']);
        $this->assertSame('Invalid type', $body['error']);
    }

    /**
     * The refusals `GatewayAddress::validate()` can give, each reached through the endpoint
     * rather than by calling the entity - what is being tested is that the controller stops
     * on the first one rather than that the entity can produce it.
     */
    #[DataProvider('theWaysAnAddressCanBeWrong')]
    public function testEveryRefusalReachesTheCaller(array $address, string $expected): void {
        $gateway = $this->gateway();

        $body = $this->decode(
            $this->withBodyFormat('json')->signedIn()->put("gateways/{$gateway->id}/gateway-addresses", [
                'values' => [$address],
            ])
        );

        $this->assertSame($expected, $body['error']);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function theWaysAnAddressCanBeWrong(): array {
        return [
            'no type' => [['type' => '', 'value' => '10.0.0.9'], 'Missing type'],
            'no value' => [['type' => 'IPAddress', 'value' => ''], 'Missing value'],
            'type too long' => [['type' => str_repeat('a', 254), 'value' => '10.0.0.9'], 'Type too long'],
            'value too long' => [['type' => 'IPAddress', 'value' => str_repeat('1', 254)], 'Value too long'],
        ];
    }

    /**
     * A refusal has to leave the gateway as it was. The bad entry is second, so the first
     * one has already been through `validate()` and is sitting in the list when the refusal
     * happens - if the saving loop ran anyway, the gateway would end up with half of what
     * was sent and no indication which half.
     */
    public function testARefusedListLeavesTheExistingAddressesAlone(): void {
        $gateway = $this->gateway();
        Fixtures::gatewayAddress(['gateway_id' => $gateway->id, 'value' => '10.0.0.1']);

        $this->withBodyFormat('json')->signedIn()->put("gateways/{$gateway->id}/gateway-addresses", [
            'values' => [
                ['type' => 'IPAddress', 'value' => '10.0.0.9'],
                ['type' => 'not a type', 'value' => '10.0.0.10'],
            ],
        ]);

        // `deletion_id IS NULL` is not optional: rows are soft deleted, so a raw query
        // without it sees everything the gateway has ever had.
        $rows = $this->db->table('gateway_addresses')
            ->where('gateway_id', $gateway->id)
            ->where('deletion_id', null)
            ->get()->getResultArray();

        $this->assertSame(['10.0.0.1'], array_column($rows, 'value'));
    }

    /**
     * This endpoint used to be the one place that did not follow the other five: it skipped
     * the body of the method and answered success with an empty resource, so a caller that
     * checks `status` alone could not tell that nothing was stored.
     */
    public function testAddressesForAnUnknownGatewayAreRefused(): void {
        $body = $this->decode(
            $this->withBodyFormat('json')->signedIn()->put('gateways/999999/gateway-addresses', [
                'values' => [['type' => 'IPAddress', 'value' => '10.0.0.9']],
            ])
        );

        $this->assertSame('unknown gateway', $body['error'] ?? null);
    }

    // </editor-fold>

    // <editor-fold desc="Annotations, which never touch the cluster">

    public function testTheAnnotationsReplaceWhatWasThere(): void {
        $gateway = $this->gateway();
        Fixtures::gatewayAnnotation(['gateway_id' => $gateway->id, 'name' => 'old', 'value' => 'gone']);

        $body = $this->putAnnotations($gateway->id, [
            ['name' => 'example.org/team', 'value' => 'platform'],
            ['name' => 'networking.gke.io/certmap', 'value' => 'store-certs'],
        ]);

        $this->assertSame('OK', $body['status']);
        $this->assertSame(
            ['example.org/team' => 'platform', 'networking.gke.io/certmap' => 'store-certs'],
            $this->reload($gateway)->getAnnotations()
        );
    }

    /**
     * The edit dialog reads a gateway by id and gets its annotations with it.
     */
    public function testAGatewayIsReadWithItsAnnotations(): void {
        $gateway = $this->gateway();
        Fixtures::gatewayAnnotation(['gateway_id' => $gateway->id]);

        $body = $this->decode($this->signedIn()->get("gateways/{$gateway->id}"));

        $this->assertSame(['example.org/team'], array_column($body['resource']['gateway_annotations'], 'name'));
    }

    /**
     * Refused here, not by the api server on the next deploy, where the message would be
     * about the Gateway and not about the field.
     */
    #[DataProvider('theWaysAnAnnotationCanBeWrong')]
    public function testAnInvalidAnnotationIsRefusedWithAReason(array $annotation, string $expected): void {
        $body = $this->putAnnotations($this->gateway()->id, [$annotation]);

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString($expected, json_encode($body));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function theWaysAnAnnotationCanBeWrong(): array {
        return [
            'no name' => [['name' => '', 'value' => 'x'], 'Missing name'],
            'a space in the name' => [['name' => 'my key', 'value' => 'x'], "Invalid name 'my key'"],
            'name too long' => [['name' => str_repeat('a', 64), 'value' => 'x'], 'Invalid name'],
            'prefix in upper case' => [['name' => 'Example.org/team', 'value' => 'x'], "Invalid prefix 'Example.org'"],
            'empty name after the prefix' => [['name' => 'example.org/', 'value' => 'x'], "Invalid name ''"],
            'kso\'s own mark' => [['name' => 'app.kubernetes.io/managed-by', 'value' => 'me'], 'is set by kso'],
        ];
    }

    /**
     * A resource has one value per key, so the second of two would win without a word.
     */
    public function testTheSameNameTwiceIsRefused(): void {
        $body = $this->putAnnotations($this->gateway()->id, [
            ['name' => 'example.org/team', 'value' => 'a'],
            ['name' => 'example.org/team', 'value' => 'b'],
        ]);

        $this->assertNotSame('OK', $body['status']);
        $this->assertSame("'example.org/team' is there twice", $body['error']);
    }

    public function testARefusedListLeavesTheExistingAnnotationsAlone(): void {
        $gateway = $this->gateway();
        Fixtures::gatewayAnnotation(['gateway_id' => $gateway->id, 'name' => 'kept', 'value' => 'yes']);

        $this->putAnnotations($gateway->id, [
            ['name' => 'example.org/team', 'value' => 'platform'],
            ['name' => 'not valid', 'value' => 'x'],
        ]);

        $this->assertSame(['kept' => 'yes'], $this->reload($gateway)->getAnnotations());
    }

    /**
     * Unlike the address endpoint next to it, which answers success and stores nothing.
     */
    public function testAnnotationsForAnUnknownGatewayAreRefused(): void {
        $body = $this->putAnnotations(999999, [['name' => 'example.org/team', 'value' => 'platform']]);

        $this->assertNotSame('OK', $body['status']);
        $this->assertStringContainsString('unknown gateway', json_encode($body));
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function gateway(): Gateway {
        return Fixtures::gateway(['name' => 'kso-gateway', 'namespace' => 'test']);
    }

    private function reload(Gateway $gateway): Gateway {
        $fresh = new Gateway();
        $fresh->find($gateway->id);

        return $fresh;
    }

    /**
     * @param array<array<string, string>> $values
     * @return array<string, mixed>
     */
    private function putAnnotations(int $gatewayId, array $values): array {
        return $this->decode(
            $this->withBodyFormat('json')->signedIn()->put("gateways/{$gatewayId}/gateway-annotations", ['values' => $values])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
