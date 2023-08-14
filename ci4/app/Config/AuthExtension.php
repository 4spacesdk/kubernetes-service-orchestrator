<?php namespace Config;

use CodeIgniter\Config\BaseConfig;

class AuthExtension extends BaseConfig {

    /*
     * Specify the database group
     */
    public $dbGroupName = 'default';

    /*
     * If true, AuthExtension will extend routes with default endpoints
     * Check CI4AuthExtension/Hooks/PreController.php for details
     */
    public $autoRoute   = true;

    /*
     * OAuth Access token lifetime in seconds
     */
    public $oauthAccessTokenLifeTime    = DAY;

}
