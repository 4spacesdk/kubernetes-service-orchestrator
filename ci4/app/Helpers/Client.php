<?php namespace App\Helpers;

use App\Entities\User;

class Client {

    public static User $user;
    public static ?string $appVersion;
    private static AuthToken $token;

    public static function Init() {
        // A client that sends nothing is an old one, from before the parameter existed, so
        // the fallback is the oldest version rather than the current one. `?app_version=`
        // is the same thing: the parameter is set but carries no version, and letting the
        // empty string through runs every later comparison against something that is not a
        // version at all. `is_string()` because `?app_version[]=x` arrives as an array.
        $sent = $_GET['app_version'] ?? '';
        self::$appVersion = is_string($sent) ? trim($sent) : '';
        if (self::$appVersion === '') {
            self::$appVersion = '1.0.0';
        }

        // Some clients send `1.2.3 (build 44)`; only the first word is the version.
        // Compared to false, not used as a truth value: a space in the first position is
        // position 0, which is falsy, so a version sent with a leading space used to
        // survive the split whole.
        $space = strpos(self::$appVersion, ' ');
        if ($space !== false) {
            self::$appVersion = substr(self::$appVersion, 0, $space);
        }
    }

    public static function SetUser(User $user) {
        self::$user = $user;
    }

    public static function SetToken(array $token) {
        self::$token = new AuthToken($token);
    }

    public static function IsAuthorized(): bool {
        return isset(self::$user) && self::$user->exists();
    }

    /**
     * The four below all go through `IsAuthorized()` first. `$user` is a typed static with
     * no default, so reading it on a request nobody signed in is a fatal error rather than
     * a "no" - and that only stays hidden as long as every single caller remembers to ask
     * `IsAuthorized()` itself.
     */
    public static function IsService(): bool {
        return self::IsAuthorized() && self::$user->type == 'service';
    }

    public static function IsDeveloper(): bool {
        return self::IsAuthorized() && in_array(self::$user->type, ['developer', 'service']);
    }

    public static function IsOwner(): bool {
        return self::IsAuthorized() && in_array(self::$user->type, ['owner', 'developer', 'service']);
    }

    public static function IsAdmin(): bool {
        return self::IsAuthorized() && in_array(self::$user->type, ['admin', 'owner', 'developer', 'service']);
    }

    /**
     * A request that arrived without a token was granted no scopes. Same reason as above:
     * `$token` is only written by `SetToken()`, and reading it before that is a 500 where
     * the answer was "no".
     */
    public static function HasScope(string $scope): bool {
        return isset(self::$token) && in_array($scope, self::$token->getScopes());
    }

}
