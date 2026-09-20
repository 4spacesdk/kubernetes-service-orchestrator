<?php namespace App\Tests\Database\Core;

use App\Core\ResourceController;
use App\DatabaseTestCase;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use DebugTool\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use RestExtension\ErrorCodes;

/**
 * The corners of the response envelope a request cannot be pointed at.
 *
 * `ResourceEnvelopeApiTest` covers the shape of a list response, `RestGetSweepTest` the
 * refusal. What is left here is either reached only through an argument the extension's own
 * call sites do not pass, or waiting for the first caller that gets it wrong - the reason
 * why is on each test.
 *
 * Calling them directly means asserting against `DebugTool\Data`, which is the bag the
 * response is built from and a process-wide static. `controller()` clears it first, or a
 * test reads the envelope the previous test left behind and passes for the wrong reason.
 */
class ResourceControllerTest extends DatabaseTestCase {

    /**
     * A plain array - rows that never came from the ORM - is counted and sent as it is.
     *
     * The promise is that such a response is indistinguishable from an ORM one: same
     * `count`, same `resources`. A client cannot tell which kind of endpoint it is talking
     * to, and must not have to. `GET /podio-integrations/{id}/fields` is the endpoint that
     * needs it - see `PodioIntegrationsApiTest` - and it used to set `resources` by hand
     * and send no count at all.
     */
    public function testARawArrayIsCountedAndSentUnchanged(): void {
        $rows = [['name' => 'first'], ['name' => 'second'], ['name' => 'third']];

        $this->controller()->_setRawResources($rows);

        $this->assertSame(3, Data::get('count'));
        $this->assertSame($rows, Data::get('resources'));
    }

    /**
     * Anything that is neither a count nor an entity collection is a mistake at the call
     * site, and it says so.
     *
     * No endpoint passes one of these, which is why this is not a request: it is the
     * behaviour waiting for the first caller that gets it wrong. It used to be dropped on
     * the floor, and the request still answered `200 OK` with no `count` and no `resources`
     * at all - the list screen drew nothing, the client could not tell an empty result from
     * a broken one, and the log said the request succeeded.
     */
    #[DataProvider('theThingsItCannotSend')]
    public function testAValueItDoesNotRecogniseIsRefusedRatherThanIgnored(mixed $items): void {
        $this->expectException(\InvalidArgumentException::class);

        $this->controller()->_setResources($items);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function theThingsItCannotSend(): array {
        return [
            'null' => [null],
            'a string' => ['deployments'],
            'a plain array' => [[['name' => 'first']]],
            'an unrelated object' => [new \stdClass()],

            // A numeric string used to pass the `is_numeric()` check and be reported as a
            // count, so a caller that handed over "5" answered a list of five with no rows
            // in it. It is not one of the two things this takes.
            'a number as a string' => ['5'],
        ];
    }

    /**
     * A count-only request answers with the number and nothing else, and it has to be a
     * number rather than a numeric string - a client reading `count` as a total compares
     * it, and the API's own contract says integer.
     */
    public function testACountIsSentAsTheNumberItIs(): void {
        $this->controller()->_setResources(5);

        $this->assertSame(5, Data::get('count'));
        $this->assertNull(Data::get('resources'), 'a count was asked for, not the rows');
    }

    /**
     * A refusal carries a machine-readable `error_code` next to the human-readable error.
     *
     * The contract the generated clients branch on: `error` is a sentence that may be
     * reworded at any time, `error_code` is the value a frontend compares against to decide
     * whether to show a "you do not have access" dialog or a generic failure. Losing it
     * turns every refusal into the generic one.
     *
     * A request reaches this through `GET` on an id that is not there - see
     * `RestGetSweepTest`. Called directly here for the message, which the extension's own
     * call sites do not pass.
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
     * An error nobody classified is a refusal, and it has to come out as one.
     *
     * `error()` defaulted to 503 Service Unavailable - "come back later" about something
     * that will be refused just as firmly next time - and `fail()` under it defaulted to
     * 200, which reads as success to anything looking at the status code rather than at the
     * envelope. They disagreed, and both were wrong. Every call site passes its own status;
     * this is what is left when one does not.
     */
    public function testAnErrorWithNoStatusOfItsOwnIsARefusal(): void {
        $controller = $this->controller();

        $this->captureTheResponse(fn() => $controller->error(ErrorCodes::InsufficientAccess));

        $this->assertSame(400, service('response')->getStatusCode());
        $this->assertSame('ERROR', Data::get('status'));
    }

    /**
     * Every resource endpoint needs a token unless its controller says otherwise.
     *
     * Nothing calls this during a request - it is read by `spark api:sync`, which writes
     * the answer into `api_routes.is_public`, and that column is what the authorization
     * hook actually enforces. So this is the default the whole public surface is measured
     * against: a new controller that extends `ResourceController` and forgets to think
     * about authentication is closed, not open. The GitHub App credentials leaked through an
     * endpoint that was open without anyone deciding it should be. See `PublicSurfaceTest`.
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
