<?php namespace App\Libraries\Audit;

use CodeIgniter\HTTP\IncomingRequest;
use RestExtension\RestRequest;

/**
 * Who is acting: the user and client the request was authorised as, where it came from, and
 * through what.
 *
 * Read from the request, which the REST hooks have filled in by the time anything is saved.
 * Work that runs later - a queued job, cron - has no request of its own, so the context it
 * was started in is carried along and restored there; a job a user set off is still theirs.
 */
final class AuditContext {

    /** The app, signed in as its own OAuth client. */
    public const string Ui = 'ui';
    /** Any other client with a token. */
    public const string Api = 'api';
    /** A request with no token: the sign-in pages and public webhooks. */
    public const string Web = 'web';
    public const string Cron = 'cron';
    public const string Queue = 'queue';
    public const string Cli = 'cli';

    /** The OAuth client the app signs in as. */
    private const string AppClient = 'webclient';

    /** @var array{user_id: ?int, client_id: ?string, ip_address: ?string, source: string}|null */
    private static ?array $restored = null;

    /**
     * @return array{user_id: ?int, client_id: ?string, ip_address: ?string, source: string}
     */
    public static function Current(): array {
        if (self::$restored !== null) {
            return self::$restored;
        }
        // By the request rather than `is_cli()`, which is also true of a test sending one.
        $request = service('request');
        if (!$request instanceof IncomingRequest) {
            return ['user_id' => null, 'client_id' => null, 'ip_address' => null, 'source' => self::Cli];
        }

        $rest = RestRequest::getInstance();
        $userId = $rest->userId ? (int) $rest->userId : null;
        $clientId = $rest->clientId ? (string) $rest->clientId : null;

        return [
            'user_id' => $userId,
            'client_id' => $clientId,
            'ip_address' => $request->getIPAddress(),
            'source' => match (true) {
                $clientId === self::AppClient => self::Ui,
                $clientId !== null => self::Api,
                default => self::Web,
            },
        ];
    }

    /**
     * Act as `$actor` - what `Current()` said where the work was started - from `$source`.
     * Null: nobody started it, the system did.
     */
    public static function Restore(?array $actor, string $source): void {
        self::$restored = [
            'user_id' => isset($actor['user_id']) ? (int) $actor['user_id'] : null,
            'client_id' => $actor['client_id'] ?? null,
            'ip_address' => $actor['ip_address'] ?? null,
            'source' => $source,
        ];
    }

    public static function Forget(): void {
        self::$restored = null;
    }

}
