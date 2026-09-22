<?php namespace App\Tests\Database\Libraries;

use App\DatabaseTestCase;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use RestExtension\Hooks;
use RestExtension\Logs;

/**
 * What the API's log tables keep, and for how long.
 *
 * `api_error_logs.headers` used to hold every header of every request that ended in an
 * exception, refused ones included: a bearer token in full, and the session cookie that fetches
 * new tokens without a password. And nothing removed a row - a hundred thousand errors in a
 * development database since April.
 */
class ApiLogsTest extends DatabaseTestCase {

    public function testTheHeadersThatCarryCredentialsAreRedactedAndTheRestKept(): void {
        $request = $this->aRequestWith([
            'Authorization' => 'Bearer secret-token',
            'cookie' => 'ci_session=secret-session',
            'X-Cron-Token' => 'secret-cron-token',
            'Accept' => 'application/json',
        ]);

        $headers = Hooks::redactedHeaders($request);

        $this->assertSame('[redacted]', $headers['Authorization']);
        $this->assertSame('[redacted]', $headers['Cookie'] ?? $headers['cookie']);
        $this->assertSame('[redacted]', $headers['X-Cron-Token'], "kso's own addition to the list");
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertStringNotContainsString('secret', json_encode($headers));
    }

    public function testRowsOlderThanTheWindowAreRemovedFromAllThreeTables(): void {
        foreach (Logs::Tables as $table) {
            $this->db->table($table)->emptyTable();
            $this->aRow($table, '-40 days');
            $this->aRow($table, '-10 days');
        }

        $removed = Logs::PruneOlderThan(30);

        foreach (Logs::Tables as $table) {
            $this->assertSame(1, $removed[$table], $table);
            $this->assertSame(1, $this->db->table($table)->countAllResults(), $table);
        }
    }

    private function aRow(string $table, string $when): void {
        $this->db->table($table)->insert(['uri' => 'https://kso.example.org/api/x', 'date' => date('Y-m-d H:i:s', strtotime($when))]);
    }

    /**
     * @param array<string, string> $headers
     */
    private function aRequestWith(array $headers): IncomingRequest {
        $request = new IncomingRequest(config('App'), new SiteURI(config('App')), null, new UserAgent());
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        return $request;
    }

}
