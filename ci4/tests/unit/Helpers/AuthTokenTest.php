<?php namespace App\Tests\Unit\Helpers;

use App\Helpers\AuthToken;
use CodeIgniter\Test\CIUnitTestCase;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

/**
 * The token the auth extension hands over, and what it says the caller may do.
 *
 * Every authorisation decision in kso ends up at `getScopes()`, so a mistake here is not a
 * wrong answer on one page - it is either a caller doing something it was never granted, or
 * a service account locked out of the api it exists to call. The fields arrive as an array
 * from the oauth store, which means they arrive as strings, and this class is the only place
 * where that is turned into something the rest of the application can trust.
 */
class AuthTokenTest extends CIUnitTestCase {

    /**
     * The oauth store hands back strings, including for the user id. Everything downstream
     * compares that id against `User::$id`, which is a real integer, so a token that kept the
     * string would quietly fail a strict comparison and the caller would be treated as
     * somebody else - or as nobody.
     */
    public function testATokenFromTheAuthExtensionIsReadIntoItsFields(): void {
        $token = new AuthToken([
            'access_token' => 'f3a9c1e2b7',
            'client_id'    => 'kso-web',
            'user_id'      => '42',
            'expires'      => 1700000000,
            'scope'        => 'system:read',
        ]);

        $this->assertSame('f3a9c1e2b7', $token->accessToken);
        $this->assertSame('kso-web', $token->clientId);
        $this->assertSame(42, $token->userId, 'the user id has to come out as an integer');
        $this->assertSame(1700000000, $token->expires);
        $this->assertSame('system:read', $token->scope);
    }

    /**
     * Nothing promises that the user id is a number. A client credentials token has no person
     * behind it at all, and `$userId` is declared `int`, so without the cast such a token is a
     * fatal error on a request that is otherwise perfectly valid. The cast turns it into zero,
     * which is an id no user in the database has - exactly what a machine caller should get.
     */
    public function testATokenWithNoRealUserBehindItGetsAnIdNoUserHas(): void {
        $this->assertSame(0, (new AuthToken(['user_id' => 'client-credentials']))->userId);
    }

    /**
     * A token that is missing a field must not pretend to have one. If the properties were
     * given defaults, a token with no client id would read as the client with an empty id and
     * a token with no user would read as user zero, both of which look like valid callers.
     * Leaving them unset means the mistake surfaces where it is made instead.
     */
    #[DataProvider('theFieldsATokenCarries')]
    public function testAFieldTheTokenDoesNotCarryIsLeftUnset(string $property): void {
        $token = new AuthToken([]);

        $this->assertFalse(
            (new ReflectionProperty(AuthToken::class, $property))->isInitialized($token),
            "{$property} was given a value the token never carried"
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theFieldsATokenCarries(): array {
        return [
            'the access token' => ['accessToken'],
            'the client id'    => ['clientId'],
            'the user id'      => ['userId'],
            'the expiry'       => ['expires'],
            'the scope'        => ['scope'],
        ];
    }

    /**
     * Scopes arrive as one space separated string because that is how oauth writes them, and
     * every caller of `getScopes()` asks `in_array()` of the result. Splitting on anything
     * else - or not splitting at all - would mean a token granted `system:read system:write`
     * matches neither of them.
     *
     *
     * @param array<int, string> $expected
     */
    #[DataProvider('theScopesATokenCanCarry')]
    public function testTheScopeClaimIsSplitIntoTheScopesItGrants(string $scope, array $expected): void {
        $this->assertSame($expected, (new AuthToken(['scope' => $scope]))->getScopes());
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function theScopesATokenCanCarry(): array {
        return [
            'a single scope'   => ['system:read', ['system:read']],
            'several scopes'   => ['system:read system:write cluster:admin', ['system:read', 'system:write', 'cluster:admin']],
            // An empty scope claim grants nothing. This is the one that has to be a list and
            // not a list holding an empty string, because an empty string is a value that
            // `in_array()` can be asked for, and a scope check against it would pass.
            'no scope granted' => ['', []],
        ];
    }

    /**
     * A token the oauth store returned without a scope at all is not the same as a token that
     * grants nothing, and this is where the difference shows: it raises rather than answering
     * an empty list. That is the safe direction - the request fails instead of being let
     * through - but it is a fatal error rather than a 401, so it is pinned here to make sure
     * nobody "fixes" it into silently granting nothing.
     */
    public function testATokenWithoutAScopeAtAllCannotBeAskedWhatItGrants(): void {
        $this->expectException(Error::class);

        (new AuthToken([]))->getScopes();
    }

}
