<?php namespace Config;

use CodeIgniter\Config\BaseConfig;

class AuthExtension extends BaseConfig {

    /*
     * Specify the database group
     */
    public string $dbGroupName = 'default';

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
     * OAuth Access token lifetime in seconds
     */
    public int $oauthRefreshTokenLifeTime = 7 * DAY;

    /*
     * Path to login page
     */
    public string $loginPage = '/login';

}
