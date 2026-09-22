<?php namespace Config;

use CodeIgniter\Config\BaseConfig;

class AuthExtension extends BaseConfig {

    /*
     * Specify the database group. The connection's default unless set - `tests` under test. Named
     * 'default', the OAuth storage's own connection reached the development database from a test.
     */
    public string $dbGroupName = '';

    public function __construct() {
        parent::__construct();
        if ($this->dbGroupName === '') {
            $this->dbGroupName = (new Database())->defaultGroup;
        }
    }

    /*
     * If true, AuthExtension will extend routes with default endpoints
     * Check CI4AuthExtension/Hooks/PreController.php for details
     */
    public bool $autoRoute = true;

    /*
     * OAuth Access token lifetime in seconds
     */
    public int $oauthAccessTokenLifeTime = 15 * MINUTE;

    /*
     * OAuth Refresh token lifetime in seconds
     */
    public int $oauthRefreshTokenLifeTime = 7 * DAY;

    /*
     * The authorization endpoint requires a PKCE code challenge. The app sends one on every
     * sign-in, and it is what stops a code handed to the login page from elsewhere from being
     * exchanged.
     */
    public bool $enforcePkce = true;

    /*
     * Access tokens are JWTs. The push connection depends on it: Centrifugo checks the app's
     * access token against kso's key set rather than asking kso.
     */
    public bool $useJwtAccessTokens = true;

    /*
     * The `iss` of id tokens and the base of the discovery document's endpoints. Empty is
     * `base_url()` - kso's own address, never the Host a request names.
     */
    public string $issuer = '';

    /*
     * Path to login page
     */
    public string $loginPage = '/login';

}
