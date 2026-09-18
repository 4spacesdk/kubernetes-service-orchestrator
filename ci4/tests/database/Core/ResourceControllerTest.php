<?php namespace App\Tests\Database\Core;

use App\Core\ResourceController;
use App\DatabaseTestCase;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use DebugTool\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use RestExtension\ErrorCodes;

/**
 * The corners of the response envelope that no endpoint reaches today.
 *
 * `ResourceEnvelopeApiTest` covers the branches a real request takes. The behaviours below
 * are part of the same contract but cannot be driven through the router, so they are
 * called on the controller directly - the reason why is on each test.
 *
 * Calling them directly means asserting against `DebugTool\Data`, which is the bag the
 * response is built from and a process-wide static. `controller()` clears it first, or a
 * test reads the envelope the previous test left behind and passes for the wrong reason.
 */
class ResourceControllerTest extends DatabaseTestCase {

    /**
     * A plain array - rows that never came from the ORM - is counted and sent as it is.
     *
     * No endpoint calls this today, so there is no request to send. It exists for an
     * endpoint that answers with something it assembled itself rather than with entities,
     * and the promise it makes is that such a response is indistinguishable from an ORM
     * one: same `count`, same `resources`. A client cannot tell which kind of endpoint it
     * is talking to, and must not have to.
     */
    public function testARawArrayIsCountedAndSentUnchanged(): void {
        $rows = [['name' => 'first'], ['name' => 'second'], ['name' => 'third']];

        $this->controller()->_setRawResources($rows);

        $this->assertSame(3, Data::get('count'));
        $this->assertSame($rows, Data::get('resources'));
    }

    /**
     * Anything that is neither a number nor an entity collection is dropped on the floor.
     *
     * There is no endpoint that passes one of these, which is why this is not a request:
     * it is the behaviour waiting for the first caller that gets it wrong. Worth pinning
     * because of how it fails - not with an error, but with a `200 OK` whose envelope has
     * no `count` and no `resources` at all. The list screen draws nothing, the client
     * cannot tell an empty result from a broken one, and the log says the request
     * succeeded.
     */
    #[DataProvider('theThingsItSilentlyIgnores')]
    public function testAValueItDoesNotRecogniseLeavesTheEnvelopeEmpty(mixed $items): void {
        $this->controller()->_setResources($items);

        $this->assertNull(Data::get('count'));
        $this->assertNull(Data::get('resources'));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function theThingsItSilentlyIgnores(): array {
        return [
            'null' => [null],
            'a string' => ['deployments'],
            'a plain array' => [[['name' => 'first']]],
            'an unrelated object' => [new \stdClass()],
        ];
    }

    /**
     * A refusal carries a machine-readable `error_code` next to the human-readable error.
     *
     * `error()` is only called from RestExtension's own `delete()`, and every controller
     * here that has a model refusing deletion overrides `delete()` with an empty body - so
     * no route reaches it and there is no request to send. It is still the contract the
     * generated clients branch on: `error` is a sentence that may be reworded at any time,
     * `error_code` is the value a frontend compares against to decide whether to show a
     * "you do not have access" dialog or a generic failure. Losing it turns every refusal
     * into the generic one.
     */
    public function testARefusalCarriesAnErrorCodeBesideTheMessage(): void {
        $controller = $this->controller();

        $this->captureTheResponse(fn() => $controller->error(
            ErrorCodes::InsufficientAccess,
            403,
            'this resource may not be deleted'
        ));

        $this->assertSame('InsufficientAccess', Data::get('error_code'));
        $this->assertSame('ERROR', Data::get('status'), 'the code sits alongside the failure, it does not replace it');
        $this->assertSame('this resource may not be deleted', Data::get('error'));
        $this->assertSame(403, service('response')->getStatusCode());
    }

    /**
     * Every resource endpoint needs a token unless its controller says otherwise.
     *
     * Nothing calls this during a request - it is read by `spark api:sync`, which writes
     * the answer into `api_routes.is_public`, and that column is what the authorization
     * hook actually enforces. So this is the default the whole public surface is measured
     * against: a new controller that extends `ResourceController` and forgets to think
     * about authentication is closed, not open. SEC-1 was an endpoint that was open
     * without anyone deciding it should be. See `PublicSurfaceTest`.
     */
    #[DataProvider('theHttpMethods')]
    public function testAResourceEndpointRequiresATokenByDefault(string $method): void {
        $this->assertTrue($this->controller()->requireAuth($method));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theHttpMethods(): array {
        return [
            'get' => ['get'],
            'post' => ['post'],
            'put' => ['put'],
            'patch' => ['patch'],
            'delete' => ['delete'],
        ];
    }

    // <editor-fold desc="Helpers">

    /**
     * `initController()` subscribes to `post_controller_constructor` and nothing
     * unsubscribes, so every controller built here would otherwise still be listening when
     * a later test sends a real request - see the same note in `ControllerTestCase`.
     */
    public function tearDown(): void {
        Events::removeAllListeners('post_controller_constructor');

        parent::tearDown();
    }

    /**
     * A controller wired up the way the framework wires one, against an empty envelope.
     */
    private function controller(): ResourceController {
        $store = (new \ReflectionClass(Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        /** @var ResponseInterface&\CodeIgniter\HTTP\Response $response */
        $response = service('response');
        // Nothing is listening for these headers, and PHPUnit has already written to the
        // output stream by the time a test runs.
        $response->pretend(true);

        $controller = new ResourceController();
        $controller->initController(service('request'), $response, service('logger'));

        return $controller;
    }

    /**
     * `error()` ends by sending the response, which writes the body to standard output.
     * Swallow it so it does not land in the middle of PHPUnit's own.
     */
    private function captureTheResponse(callable $act): void {
        ob_start();
        try {
            $act();
        } finally {
            ob_end_clean();
        }
    }

    // </editor-fold>

}
