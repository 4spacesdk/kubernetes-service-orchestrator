<?php namespace App\Entities;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use DatabaseDrivers;
use DebugTool\Data;
use App\Core\Entity;
use App\Entities\Concerns\EncryptsFields;
use App\Entities\Concerns\WriteOnlySecrets;

/**
 * Class DatabaseService
 * @package App\Entities
 * @property string $name
 * @property string $driver
 * @property string $host
 * @property string $azure_host
 * @property string $port
 * @property string $user
 * @property string $pass write-only, see WriteOnlySecrets
 * @property bool $has_pass
 * @property bool $tls
 * @property bool $tls_verify MySQL: the server's certificate is checked - signed by the CA and naming the host. Off, it is not checked at all
 * @property string $tls_ca PEM. MySQL; without one the connection is encrypted but the server not checked
 * @property string $tls_client_cert PEM, MySQL
 * @property string $tls_client_key PEM, MySQL; write-only, see WriteOnlySecrets
 * @property bool $has_tls_client_key
 *
 * Many
 * @property Workspace $workspaces
 * @property Deployment $deployments
 */
class DatabaseService extends Entity {

    public const array EncryptedFields = self::SecretFields;

    use EncryptsFields;

    public const array SecretFields = ['pass', 'tls_client_key'];

    use WriteOnlySecrets;

    public $hiddenFields = self::SecretFields;

    public static function patch($id, $data) {
        return parent::patch($id, self::keepStoredSecrets($data));
    }

    public function getDatabaseUser($name): string {
        if (getenv('IS_AZURE')) {
            return  "{$name}@{$this->azure_host}";
        } else {
            return $name;
        }
    }

    /**
     * A connection to the service, over TLS when it asks for it.
     *
     * MySQLi reads the certificates from files, and only while connecting, so they are written
     * to temporary files that are removed as soon as the connection is made - the client key is
     * on disk only while connecting. That connection is therefore made here rather than on the
     * first query, and not shared: its settings name files that are gone, so it cannot reconnect.
     *
     * MSSQL has no CA of its own per connection. `Encrypt` checks the server against the
     * image's trust store, which holds the public CAs Azure SQL is signed by.
     */
    public function prepareConnection(): BaseConnection {
        $files = [];
        try {
            $db = Database::connect($this->connectionSettings($files), false);
            if ($files !== []) {
                $db->initialize();
            }
            return $db;
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @param list<string> $files the temporary files written, for the caller to remove
     */
    private function connectionSettings(array &$files): array {
        return [
            'DSN'      => '',
            'hostname' => $this->host,
            'username' => $this->user,
            'password' => $this->pass,
            'database' => '',
            'DBDriver' => match ($this->driver) {
                \DatabaseDrivers::MySQL => 'MySQLi',
                \DatabaseDrivers::MSSQL => 'SQLSRV',

                // Said in words rather than left to `UnhandledMatchError`. Nothing
                // validates the column - `POST /database_services` takes any string - so a
                // driver kso does not know is something a caller can store, and "the
                // connection failed" would send whoever reads it looking at a host, a port
                // and a password that are all perfectly correct.
                default => throw new DatabaseException(
                    "Unknown database driver '{$this->driver}'"
                ),
            },
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => (ENVIRONMENT !== 'production'),
            'charset'  => 'utf8',
            'DBCollat' => 'utf8_general_ci',
            'swapPre'  => '',
            'encrypt'  => $this->tlsSettings($files),
            'compress' => false,
            'strictOn' => false,
            'failover' => [],

            // Cast, and it is the difference between this service connecting and not. A
            // property read off a row is a string even where the column is an int, and
            // CodeIgniter's MySQLi driver is `declare(strict_types=1)`, so
            // `mysqli::real_connect()` refused `"3306"` where it wants `?int`. Every MySQL
            // service answered the connection test with no, however correct its settings,
            // and `DatabaseStep` could not create a tenant's database either.
            //
            // An empty port stays harmless: the driver reads `0` as "not set" and uses its
            // own default, the same as it did for `""`.
            'port' => (int) $this->port,
        ];
    }

    /**
     * @param list<string> $files
     * @return array<string, string|bool>|bool the driver's `encrypt`
     */
    private function tlsSettings(array &$files): array|bool {
        if (!$this->tls) {
            return false;
        }
        if ($this->driver === \DatabaseDrivers::MSSQL) {
            return true;
        }

        // An empty array is TLS too, just without checking who answered.
        $settings = [];
        foreach (['ssl_ca' => 'tls_ca', 'ssl_cert' => 'tls_client_cert', 'ssl_key' => 'tls_client_key'] as $key => $field) {
            $pem = trim((string) $this->{$field});
            if ($pem !== '') {
                $files[] = $settings[$key] = self::TemporaryFile($pem);
            }
        }
        if ($settings !== []) {
            $settings['ssl_verify'] = (bool) $this->tls_verify;
        }

        return $settings;
    }

    /**
     * Readable by this process only - `tempnam()` makes it 0600.
     */
    private static function TemporaryFile(string $pem): string {
        $file = tempnam(sys_get_temp_dir(), 'kso-db-tls-');
        file_put_contents($file, $pem . "\n");
        return $file;
    }

    public function testConnection(): bool {
        return $this->connectionProblem() === null;
    }

    /**
     * Why this service cannot be connected to, in the server's or the driver's words, or
     * null when it can. Part of the answer to the test button rather than of the debug log,
     * which is not sent outside development.
     */
    public function connectionProblem(): ?string {
        try {
            $db = $this->prepareConnection();
            switch ($this->driver) {
                case DatabaseDrivers::MySQL:
                    $test = $db->query('SELECT version()');
                    Data::debug($test);
                    break;
                case DatabaseDrivers::MSSQL:
                    $test = $db->query('SELECT 1');
                    Data::debug($test);
                    break;
            }
            return null;
        } catch (\Throwable $e) {
            // Throwable, not `\Exception|DatabaseException`. The whole point of this method
            // is that a service which cannot be connected to answers no rather than failing
            // the request - and the most reachable way for it to fail was not an exception
            // at all: a `driver` the `match` in `prepareConnection()` does not know is an
            // `UnhandledMatchError`, which is an `\Error`. Nothing validates that column, so
            // a service stored with one was a 500 on the button for any signed-in caller.
            return $e->getMessage();
        }
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DatabaseService[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
