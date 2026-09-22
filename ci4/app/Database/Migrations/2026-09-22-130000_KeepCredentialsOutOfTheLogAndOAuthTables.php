<?php namespace App\Database\Migrations;

use AuthExtension\Migration\Upgrade_1_3_0;
use CodeIgniter\Database\Migration;
use Config\Database;
use RestExtension\Hooks;

/**
 * CI4AuthExtension v1.3.0 and CI4RESTExtension v1.0.14 keep no credentials in their tables. This
 * brings the rows written before them in line, and schedules the cleanups nothing did before.
 *
 * - The OAuth tables: `Upgrade_1_3_0` hashes the stored tokens, authorization codes and client
 *   secrets, and encrypts the signing key. Sessions survive it; it cannot be undone.
 * - The API log tables: the access token out of every row, and the headers that carry a credential
 *   redacted in `api_error_logs` - a bearer token in full, and the session cookie. In portions:
 *   a development database had a hundred thousand error rows.
 * - Two nightly jobs: the log tables kept to a month, and expired tokens removed.
 */
class KeepCredentialsOutOfTheLogAndOAuthTables extends Migration {

    private const int Portion = 1000;

    public function up() {
        Upgrade_1_3_0::migrateUp();

        $db = Database::connect();
        foreach (['api_access_logs', 'api_error_logs', 'api_blocked_logs'] as $table) {
            do {
                $db->query("UPDATE {$table} SET access_token = NULL WHERE access_token IS NOT NULL LIMIT " . self::Portion * 10);
            } while ($db->affectedRows() > 0);
        }
        $this->redactStoredHeaders();

        $this->aCronJob(\CronJobIds::CleanupApiLogs, 'app:cleanup-api-logs', '50 3 * * *');
        $this->aCronJob(\CronJobIds::CleanupOAuthTokens, 'app:cleanup-oauth-tokens', '55 3 * * *');
    }

    private function redactStoredHeaders(): void {
        $db = Database::connect();
        $redacted = array_map('strtolower', array_merge(Hooks::RedactedHeaders, config('RestExtension')->redactedHeaders ?? []));

        $lastId = 0;
        do {
            $rows = $db->table('api_error_logs')
                ->select('id, headers')
                ->where('id >', $lastId)
                ->orderBy('id', 'asc')
                ->limit(self::Portion)
                ->get()->getResultArray();

            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $headers = json_decode((string) $row['headers'], true);
                if (!is_array($headers)) {
                    continue;
                }
                $changed = false;
                foreach ($headers as $name => $value) {
                    if (in_array(strtolower((string) $name), $redacted, true) && $value !== '[redacted]') {
                        $headers[$name] = '[redacted]';
                        $changed = true;
                    }
                }
                if ($changed) {
                    $db->table('api_error_logs')->where('id', $row['id'])->update(['headers' => json_encode($headers, JSON_PRETTY_PRINT)]);
                }
            }
        } while (count($rows) === self::Portion);
    }

    private function aCronJob(int $id, string $command, string $schedule): void {
        $db = Database::connect();
        if ($db->table('cron_jobs')->where('id', $id)->countAllResults() > 0) {
            return;
        }
        $db->table('cron_jobs')->insert([
            'id' => $id,
            'name' => $command,
            'schedule' => $schedule,
            'command' => $command,
            'duplicates' => 1,
        ]);
    }

    public function down() {

    }

}
