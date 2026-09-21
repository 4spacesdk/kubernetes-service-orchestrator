<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;

/**
 * The root of the API, which is a link to Swagger when Swagger is on, and nothing otherwise.
 *
 * Small, but not nothing: it is public, like the OpenAPI document it points to, and the
 * link it prints is built from `base_url()`, so it is the one place where a misconfigured base url
 * shows up as a broken page rather than as a wrong value buried in a manifest.
 *
 * The href is asserted against `base_url()` rather than against a literal host, so the
 * test says what the page promises without pinning it to the developer's environment.
 */
class HomeApiTest extends ControllerTestCase {

    public function testTheFrontPageIsALinkToSwaggerWhenSwaggerIsOn(): void {
        putenv('SWAGGER_ENABLED=true');
        try {
            $response = $this->get('home');
        } finally {
            putenv('SWAGGER_ENABLED');
        }

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertSame(
            "<a href='" . base_url('swagger') . "'>Swagger</a>",
            (string) $response->response()->getBody()
        );
    }

    /**
     * With Swagger off there is nothing to link to, and the page says nothing about it.
     */
    public function testTheFrontPageIsEmptyWhenSwaggerIsOff(): void {
        $response = $this->get('home');

        $this->assertSame('', (string) $response->response()->getBody());
    }

    /**
     * The controller declares itself public and the table agrees. `requireAuth()` has no
     * call sites - the column is what the hook reads - so both halves are held
     * here, and closing one without the other fails.
     */
    public function testTheFrontPageIsPublicInBothTheCodeAndTheTable(): void {
        $this->assertFalse((new \App\Controllers\Home())->requireAuth('index'));

        // `from` is a reserved word, so the column is quoted by hand.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->where('method', 'get')
            ->get()
            ->getResultArray();

        $this->assertSame(1, (int) array_column($rows, 'is_public', 'from')['home']);
    }

}
