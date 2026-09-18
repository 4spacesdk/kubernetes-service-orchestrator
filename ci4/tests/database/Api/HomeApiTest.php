<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;

/**
 * The root of the API, which is a link to Swagger and nothing else.
 *
 * Small, but not nothing: it is public (SEC-14 covers why that matters), and the link it
 * prints is built from `base_url()`, so it is the one place where a misconfigured base url
 * shows up as a broken page rather than as a wrong value buried in a manifest.
 *
 * The href is asserted against `base_url()` rather than against a literal host, so the
 * test says what the page promises without pinning it to the developer's environment.
 */
class HomeApiTest extends ControllerTestCase {

    public function testTheFrontPageIsALinkToSwagger(): void {
        $response = $this->get('home');

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertSame(
            "<a href='" . base_url('swagger') . "'>Swagger</a>",
            (string) $response->response()->getBody()
        );
    }

    /**
     * The controller declares itself public and the table agrees. `requireAuth()` has no
     * call sites - the column is what the hook reads (SEC-11) - so both halves are held
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
