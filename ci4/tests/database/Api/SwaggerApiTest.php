<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;

/**
 * The two endpoints that describe this installation to anyone who asks.
 *
 * `GET /swagger` is the Swagger UI page and `GET /swagger/openapi` is the document it
 * reads. Both answer without a token - the page fetches the document before anyone can sign
 * in through it - so they are only there in development, or where an installation turns them
 * on with `SWAGGER_ENABLED=true`. Elsewhere they answer 404: the document is every endpoint,
 * every model and every field of the API.
 *
 * Here the controller's own `requireAuth()` and the `api_routes.is_public` column agree,
 * which is worth holding on to precisely because they do not have to: the column is what
 * the hook reads, and the method has no call sites at all. Putting them behind login means
 * changing the column in a migration; the test below is what makes that a deliberate act
 * rather than an accident either way.
 *
 * **`openapi()` itself is not exercised here, on purpose.** One request to it costs 5.2
 * seconds - it reflects over every controller and every model on each call, with no cache
 * in between - and the whole unit+database run has a ten second budget in both cloudbuild
 * files. Half the budget for one endpoint is not a trade worth making, so its body stays
 * uncovered and the cost is reported instead. If the gap is closed by taking the route out
 * of `api_routes`, the method becomes unreachable and the question goes away.
 */
class SwaggerApiTest extends ControllerTestCase {

    /**
     * A fresh router and response for every request. The `swagger` row names no method, so
     * the router falls back on the one it holds - and the harness keeps one router for the
     * whole run, so after `swagger/openapi` the page was answered by `openapi()`.
     */
    public function call(string $method, string $path, ?array $params = null) {
        \CodeIgniter\Config\Services::resetSingle('router');
        \CodeIgniter\Config\Services::resetSingle('response');

        return parent::call($method, $path, $params);
    }

    public function tearDown(): void {
        putenv('SWAGGER_ENABLED');

        parent::tearDown();
    }

    /**
     * Neither the page nor the document is served unless it is turned on. The suite runs as
     * `testing`, which stands in for every environment that is not development.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('theTwoRoutes')]
    public function testSwaggerIsNotServedUnlessItIsTurnedOn(string $path): void {
        $response = $this->get($path);

        $this->assertSame(404, $response->response()->getStatusCode());
        $this->assertStringNotContainsString('swagger-ui', (string) $response->response()->getBody());
    }

    public static function theTwoRoutes(): array {
        return ['the page' => ['swagger'], 'the document' => ['swagger/openapi']];
    }

    /**
     * Anything other than `true` leaves it off.
     */
    public function testOnlyTrueTurnsItOn(): void {
        putenv('SWAGGER_ENABLED=1');
        $this->assertFalse(\App\Controllers\Swagger::IsEnabled());

        putenv('SWAGGER_ENABLED=TRUE');
        $this->assertTrue(\App\Controllers\Swagger::IsEnabled());
    }

    /**
     * What the controller says about itself, held against what is enforced.
     *
     * `requireAuth()` returns false for every method name it is handed, so the controller
     * claims the whole of itself is public - and for once the table agrees. The assertion
     * on an invented method name is not padding: the method ignores its argument entirely,
     * so anything added to this controller later is public by default.
     */
    public function testTheApiDescriptionIsPublicInBothTheCodeAndTheTable(): void {
        $controller = new \App\Controllers\Swagger();

        $this->assertFalse($controller->requireAuth('index'));
        $this->assertFalse($controller->requireAuth('openapi'));
        $this->assertFalse($controller->requireAuth('anything-added-later'), 'the argument is ignored');

        // `from` is a reserved word, so the column is quoted by hand.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->where('method', 'get')
            ->whereIn('from', ['swagger', 'swagger/openapi'])
            ->get()
            ->getResultArray();

        $public = array_column($rows, 'is_public', 'from');

        $this->assertSame(1, (int) $public['swagger'], 'the UI page needs a login now - update this test and the note above');
        $this->assertSame(1, (int) $public['swagger/openapi'], 'the document needs a login now - update this test and the note above');
    }

    /**
     * The page renders for a caller with no token at all.
     *
     * It is also the one route in the table whose target names no method:
     * `App\Controllers\Swagger::`. That resolves only because CodeIgniter falls back to the
     * default method when the string after `::` is empty, so the page works by accident of
     * the framework rather than by what the row says. Asserted through a real request for
     * that reason - reading the row would not tell you whether it resolves.
     */
    public function testTheSwaggerPageIsServedToACallerWithNoTokenWhenTurnedOn(): void {
        putenv('SWAGGER_ENABLED=true');

        $response = $this->get('swagger');

        $this->assertSame(200, $response->response()->getStatusCode());

        $body = (string) $response->response()->getBody();
        $this->assertStringContainsString('<div id="swagger-ui">', $body);
        $this->assertStringContainsString('swagger/openapi', $body, 'the page names the document it loads');
    }

}
