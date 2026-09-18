<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use RestExtension\Exceptions\UnauthorizedException;

/**
 * The list of environments a deployment can be put in.
 *
 * It is a constant in `app/Constants/Kso.php` served as a resource list, and the frontend
 * fills a dropdown from it. The shape is the whole of it: each entry is an object with a
 * `name`, not a bare string, and a change to either the list or the shape is a change the
 * frontend has to be told about.
 */
class EnvironmentsApiTest extends ControllerTestCase {

    public function testTheListIsTheEnvironmentsTheApplicationKnows(): void {
        $body = $this->decode($this->signedIn()->get('environments'));

        $this->assertSame('OK', $body['status']);
        $this->assertSame(
            [['name' => 'development'], ['name' => 'production']],
            $body['resources'],
            'each entry is an object with a name, not a bare string'
        );
    }

    /**
     * The values are the constants the rest of the application compares against, so the
     * endpoint is held to them rather than to a literal list - a third environment added
     * to `Environments::All()` should appear here without this test being edited, and a
     * renamed one should not appear anywhere else.
     */
    public function testTheListIsExactlyTheConstant(): void {
        $body = $this->decode($this->signedIn()->get('environments'));

        $this->assertSame(
            \Environments::All(),
            array_column($body['resources'], 'name')
        );
    }

    /**
     * Not public. Harmless in itself, but the table is what decides (SEC-11), so the
     * declaration and the column are held against each other.
     */
    public function testTheListNeedsAToken(): void {
        $this->assertTrue((new \App\Controllers\Environments())->requireAuth('get'));

        // `from` is a reserved word, so the column is quoted by hand.
        $rows = $this->db->table('api_routes')
            ->select('`from`, is_public', false)
            ->where('method', 'get')
            ->get()
            ->getResultArray();

        $this->assertSame(0, (int) array_column($rows, 'is_public', 'from')['environments']);

        $this->expectException(UnauthorizedException::class);
        $this->get('environments');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
