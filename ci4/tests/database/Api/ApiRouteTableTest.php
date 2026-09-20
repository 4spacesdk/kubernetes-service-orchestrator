<?php namespace App\Tests\Database\Api;

use App\DatabaseTestCase;

/**
 * The route table held against the code it points at.
 *
 * `api_routes` is data: written once by a migration and never checked against the
 * controllers again. Nothing keeps the two in step, so a row outlives the method it names,
 * and an endpoint exists that nobody decided should - see the note on `requireAuth()` in
 * `PublicSurfaceTest`. This is the sweep that would have caught it.
 */
class ApiRouteTableTest extends DatabaseTestCase {

    /**
     * No route may point at a method the API is supposed to ignore.
     *
     * `@ignore true` is how a controller says a verb is not part of the API, and the method
     * under it is left empty. The annotation is read by the route *generator* and by
     * swagger; it does not remove a row that is already in the table. Twenty rows were,
     * written by `ApiRoute::addResourceController()` from 2023 onwards and never removed.
     *
     * What they answered is the reason this is a sweep and not a note: an empty method never
     * reaches `success()`, so the response has no envelope at all - no `status`, no `error`,
     * no body. A generated client calling `PUT /webhooks/1` was told, as clearly as the
     * protocol allows, that the write had gone through.
     */
    public function testNoRoutePointsAtAMethodTheApiIgnores(): void {
        $this->assertSame([], $this->routesPointingAtIgnoredMethods());
    }

    /**
     * And the sweep above can see one, so a green result means the table is clean rather
     * than that the check is looking in the wrong place.
     *
     * The row is written inside the test's own transaction and rolled back with it.
     */
    public function testTheSweepWouldCatchARouteThatCameBack(): void {
        $this->db->table('api_routes')->insert([
            'method' => 'put',
            'from' => 'webhooks/([0-9]+)',
            'to' => 'App\\Controllers\\Webhooks::put/$1',
            'cacheable' => 0,
            'is_public' => 0,
        ]);

        $this->assertSame(
            ['put webhooks/([0-9]+) -> Webhooks::put'],
            $this->routesPointingAtIgnoredMethods()
        );
    }

    /**
     * The empty methods themselves stay, and this says why: each one overrides the write
     * the resource controller would otherwise give every entity. `Webhooks::put()` is the
     * sharpest - the trait's `put()` replaces every column, so a partial body would blank a
     * subscriber's bearer token - and deleting it as dead code would open that the moment a
     * row came back.
     */
    public function testTheIgnoredMethodsAreStillThereToOverrideTheResourceController(): void {
        foreach ([
            \App\Controllers\Webhooks::class,
            \App\Controllers\Workspaces::class,
            \App\Controllers\Deployments::class,
            \App\Controllers\DatabaseServices::class,
            \App\Controllers\OAuthClients::class,
            \App\Controllers\Users::class,
            \App\Controllers\Gateways::class,
        ] as $controller) {
            $this->assertTrue(
                (new \ReflectionMethod($controller, 'put'))->getDeclaringClass()->getName() === $controller,
                "{$controller} no longer overrides put(), so the resource controller's own is routed"
            );
        }
    }

    /**
     * No route may point at a method that is not there.
     *
     * Five did. `workspaces/{id}/requestSupportLogin` came from the initial migration in
     * 2023 and outlived its method by years; `deployment-specifications/{id}/ingress-rule-paths`
     * and the three `systems/default_*` ones are the same story without a date on it. Every
     * call on them is a `PageNotFoundException` - there is nothing behind them - but they
     * were generated into the API client and published in the OpenAPI document all the same.
     *
     * This is the sweep that makes the route table answerable to the code, rather than a
     * list somebody has to remember to prune.
     */
    public function testNoRoutePointsAtAMethodThatIsGone(): void {
        $this->assertSame([], $this->routesPointingAtMethodsThatAreGone());
    }

    /**
     * And this one can see such a row, so green above means the table is clean rather than
     * that the check looks in the wrong place. Written inside the test's own transaction.
     */
    public function testTheSweepWouldCatchARouteToAMethodThatIsGone(): void {
        $this->db->table('api_routes')->insert([
            'method' => 'get',
            'from' => 'workspaces/([0-9]+)/requestSupportLogin',
            'to' => 'App\\Controllers\\Workspaces::requestSupportLogin/$1',
            'cacheable' => 0,
            'is_public' => 0,
        ]);

        $this->assertSame(
            ['get workspaces/([0-9]+)/requestSupportLogin -> Workspaces::requestSupportLogin'],
            $this->routesPointingAtMethodsThatAreGone()
        );
    }

    // <editor-fold desc="Helpers">

    /**
     * @return string[] one line per offending row, empty when there are none
     */
    private function routesPointingAtIgnoredMethods(): array {
        $offenders = [];

        foreach ($this->db->table('api_routes')->orderBy('id')->get()->getResultArray() as $row) {
            if (!preg_match('/^App\\\\Controllers\\\\(\w+)::(\w+)/', (string) $row['to'], $matches)) {
                continue;
            }

            [, $controller, $method] = $matches;
            $class = "App\\Controllers\\{$controller}";
            if (!method_exists($class, $method)) {
                // A row naming a method that is gone is a different fault, sweep and all -
                // see `routesPointingAtMethodsThatAreGone()`. This one is only about the
                // methods that are deliberately empty.
                continue;
            }

            $doc = (string) (new \ReflectionMethod($class, $method))->getDocComment();
            if (str_contains($doc, '@ignore true')) {
                $offenders[] = "{$row['method']} {$row['from']} -> {$controller}::{$method}";
            }
        }

        return $offenders;
    }

    /**
     * @return string[] one line per row naming a method that does not exist
     */
    private function routesPointingAtMethodsThatAreGone(): array {
        $offenders = [];

        foreach ($this->db->table('api_routes')->orderBy('id')->get()->getResultArray() as $row) {
            // `\w*`, not `\w+`: the swagger route is written with an empty method part, and
            // CodeIgniter routes that to `index()`. It is a real endpoint - requiring a
            // method name here would skip it rather than check it.
            if (!preg_match('/^App\\\\Controllers\\\\(\w+)::(\w*)/', (string) $row['to'], $matches)) {
                continue;
            }

            [, $controller, $method] = $matches;
            $class = "App\\Controllers\\{$controller}";
            $method = $method === '' ? 'index' : $method;

            if (!class_exists($class) || !method_exists($class, $method)) {
                $offenders[] = "{$row['method']} {$row['from']} -> {$controller}::{$method}";
            }
        }

        return $offenders;
    }

    // </editor-fold>

}
