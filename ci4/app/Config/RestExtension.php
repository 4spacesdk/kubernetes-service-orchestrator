<?php namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\HTTP\Request;
use RestExtension\AuthorizeResponse;

class RestExtension extends BaseConfig {

    /*
     * Enabling this feature requires you to
     * - Store routes in the database
     * - Assign scopes to the routes
     * This will allow RestExtension to authorize every request against OAuth2 Scopes
     */
    public $enableApiRouting        = TRUE;

    /*
     * Track every access to the API.
     * Consider adding a CronJob to periodically cleanup this table
     */
    public $enableAccessLog         = TRUE;

    /*
     * Track blocked requests.
     * Ex. if you use scopes to authorize
     */
    public $enableBlockedLog        = TRUE;

    /*
     * Track errors
     */
    public $enableErrorLog          = TRUE;

    /*
     * Enable rate limit
     */
    public $enableRateLimit         = FALSE;

    /*
     * Hourly rate limit
     * Requires enableAccessLog to be TRUE
     */
    public $defaultRateLimit        = 0;

    /*
     * Daily usage report
     */
    public $enableUsageReporting    = TRUE;


    /**
     * Apply function to authenticate $request.
     * access_token is placed in either a header called Authorization or a GET-parameter called access_token
     * @param Request $request
     * @param string $scope
     * @return object
     */
    public function authorize(Request $request, $scope = null) {
        /**
         * If AuthExtension is part of this project you could do something like
         */
        return (object)\AuthExtension\AuthExtension::authorize($scope);
    }

    /*
     * Provide Route for Typescript model exporter
     */
    public $typescriptModelExporterRoute    = 'export/models';

    /*
     * Provide Route for Typescript API exporter
     */
    public $typescriptAPIExporterRoute      = 'export/api/ts';

    /*
     * Provide Route for Typescript API exporter
     */
    public $vueAPIExporterRoute             = 'export/api/vue';

    /*
     * Provide Route for Xamarin model exporter
     */
    public $xamarinModelExporterRoute       = 'xamarin/models';

    /*
     * Provide Namespace for Xamarin API
     */
    public $xamarinAPIClassName             = 'Api';
    public $xamarinAPINamespace             = 'Deploy.Http';
    public $xamarinBaseAPINamespace         = 'Deploy.Http';

    /*
     * Provide Route for Xamarin API exporter
     */
    public $xamarinAPIExporterRoute         = 'xamarin/api';

    /*
     * Provide Namespace for Api request and response Interfaces
     */
    public $apiInterfaceNamespace           = 'App\Interfaces';

    /*
     * Provide base namespace for Controllers to be used in Api export
     */
    public $apiControllerNamespace          = 'App\Controllers';

    /*
     * Provide destination for TypeScript Models to be placed when executed as Command
     */
    public $typescriptModelExporterDestination  = '~/Desktop/ModelExporter';

    /*
     * Provide destination for TypeScript API to be placed when executed as Command
     */
    public $typescriptAPIExporterDestination    = '~/Desktop/APIExporter';

}
