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
     * **Today's behaviour, and the odd ones out.** The two Kubernetes panels have no catch
     * at all, so the exception leaves the controller and the caller gets whatever the
     * framework makes of it rather than the `status: ERROR` its four siblings return.
     *
     * Pinned rather than endorsed: the difference is invisible in the code until you line
     * the six methods up, and it is the sort of thing that is fixed by copying the catch
     * from the method above it.
     */
    #[DataProvider('theEndpointsThatThrow')]
    public function testTheKubernetesPanelsLetTheFailureEscape(string $path): void {
        $gateway = $this->gateway();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('missing KUBERNETES_AUTH');

        $this->signedIn()->get("gateways/{$gateway->id}/{$path}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theEndpointsThatThrow(): array {
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
     * An unknown gateway is the one place where this endpoint does not follow the other
     * five: they refuse, this one skips the body of the method and answers success with an
     * empty resource. Pinned because the two shapes sit in the same file, and because a
     * caller that checks `status` alone cannot tell that nothing was stored.
     */
    public function testAddressesForAnUnknownGatewayAreAnsweredWithSuccess(): void {
        $body = $this->decode(
            $this->withBodyFormat('json')->signedIn()->put('gateways/999999/gateway-addresses', [
                'values' => [['type' => 'IPAddress', 'value' => '10.0.0.9']],
            ])
        );

        $this->assertSame('OK', $body['status']);
        $this->assertNull($body['resource']['id'], 'the resource is an empty gateway, not a stored one');
    }

    // </editor-fold>

    // <editor-fold desc="Fixtures">

    private function gateway(): Gateway {
        return Fixtures::gateway(['name' => 'kso-gateway', 'namespace' => 'test']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
