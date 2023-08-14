<?php namespace App\Helpers;

use App\Entities\User;

class Client {

    public static User $user;
    public static ?string $appVersion;
    private static AuthToken $token;

    public static function Init() {
        self::$appVersion = isset($_GET['app_version']) ? $_GET['app_version'] : '1.0.0';
        if (strpos(self::$appVersion, ' ')) {
            $parts = explode(' ', self::$appVersion);
            self::$appVersion = $parts[0];
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

    public static function IsService(): bool {
        return self::$user->type == 'service';
    }

    public static function IsDeveloper(): bool {
        return in_array(self::$user->type, ['developer', 'service']);
    }

    public static function IsOwner(): bool {
        return in_array(self::$user->type, ['owner', 'developer', 'service']);
    }

    public static function IsAdmin(): bool {
        return in_array(self::$user->type, ['admin', 'owner', 'developer', 'service']);
    }

    public static function HasScope(string $scope): bool {
        return in_array($scope, self::$token->getScopes());
    }

}
