<?php namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;
use Config\Database;

/**
 * Writes every sign-in attempt to `sign_in_attempts`, and refuses a username once it has
 * failed too many times in a row.
 *
 * The count is read from the same rows the audit log is made of, rather than kept beside
 * it, so the two cannot disagree - and it survives a restart and is shared by every pod.
 *
 * Per username and not per address: kso has no trusted proxies configured, so behind an
 * ingress every visitor has the ingress controller's address, and a limit per address
 * would be one limit for everybody. The address is recorded all the same.
 *
 * The price is that someone who knows a username can keep that user out for a while by
 * failing on purpose. Bounded, and the lesser evil: without a limit a password can be
 * guessed as fast as the server answers. Names that have no account are counted exactly
 * like names that do, so being refused says nothing about whether the account exists.
 *
 * The password and the second-factor code are counted separately, as `$step`. A six-digit
 * code has a million values, and without a count of its own it could be run through by
 * anyone who had the password.
 */
class LoginThrottle {

    public const int MaxFailures = 10;

    /**
     * How far back failures are counted. Also roughly how long a refusal lasts: it ends
     * once the oldest of the failures behind it falls out of the window.
     */
    public const int WindowSeconds = 15 * 60;

    public const string
        Password = 'password',
        Code = 'code',
        PasswordReset = 'password_reset';

    /**
     * Links to choose a new password, per address and hour. Each one is a mail to somebody's
     * inbox, and the form is public: without a limit it could send a known user as many as
     * the server answers. Three is enough for a link that went to spam and one more.
     */
    public const int MaxPasswordResets = 3;

    public const int PasswordResetWindowSeconds = 60 * 60;

    /**
     * Failures since the last success, within the window. By id and not by time, because a
     * success and a failure in the same second would otherwise be in no particular order.
     */
    public static function IsRefused(string $step, string $username): bool {
        $db = Database::connect();

        $lastSuccess = $db->table('sign_in_attempts')
            ->selectMax('id')
            ->where('username', self::Clip($username, 255))
            ->where('step', $step)
            ->where('succeeded', 1)
            ->get()->getRow('id');

        $failures = $db->table('sign_in_attempts')
            ->where('username', self::Clip($username, 255))
            ->where('step', $step)
            ->where('succeeded', 0)
            ->where('refused', 0)
            ->where('id >', (int) $lastSuccess)
            ->where('created >', date('Y-m-d H:i:s', time() - self::WindowSeconds))
            ->countAllResults();

        return $failures >= self::MaxFailures;
    }

    /**
     * Every request counts, whether or not the address has an account - otherwise being
     * refused would say that it does.
     */
    public static function IsPasswordResetRefused(string $username): bool {
        return Database::connect()->table('sign_in_attempts')
            ->where('username', self::Clip($username, 255))
            ->where('step', self::PasswordReset)
            ->where('refused', 0)
            ->where('created >', date('Y-m-d H:i:s', time() - self::PasswordResetWindowSeconds))
            ->countAllResults() >= self::MaxPasswordResets;
    }

    /**
     * @param bool $sent whether a mail went out - there is no account behind the address otherwise
     */
    public static function RecordPasswordReset(string $username, ?int $userId, bool $sent): void {
        self::Record(self::PasswordReset, $username, $userId, succeeded: $sent);
    }

    public static function RecordSuccess(string $step, string $username, int $userId): void {
        self::Record($step, $username, $userId, succeeded: true);
    }

    public static function RecordFailure(string $step, string $username, ?int $userId): void {
        self::Record($step, $username, $userId, succeeded: false);

        log_message('warning', 'Failed sign-in ({step}) for "{username}"', [
            'step' => $step,
            'username' => $username,
        ]);
    }

    /**
     * An attempt turned away without being checked. Kept for the log, and not counted as a
     * failure: a refusal would otherwise be kept alive for as long as someone kept trying.
     */
    public static function RecordRefusal(string $step, string $username, ?int $userId): void {
        self::Record($step, $username, $userId, succeeded: false, refused: true);
    }

    private static function Record(string $step, string $username, ?int $userId, bool $succeeded, bool $refused = false): void {
        $request = service('request');
        $isHttp = $request instanceof IncomingRequest;

        Database::connect()->table('sign_in_attempts')->insert([
            'username' => self::Clip($username, 255),
            'user_id' => $userId,
            'step' => $step,
            'succeeded' => (int) $succeeded,
            'refused' => (int) $refused,
            'ip_address' => $isHttp ? self::Clip($request->getIPAddress(), 45) : '',
            'user_agent' => $isHttp ? self::Clip($request->getUserAgent()->getAgentString(), 511) : '',
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Cut to the column, so that a long username is still counted and logged rather than
     * refused by the database. The lookup behind the sign-in does not care about case, and
     * neither does the column's collation, so the count does not either.
     */
    private static function Clip(string $value, int $length): string {
        return mb_substr($value, 0, $length);
    }

}
