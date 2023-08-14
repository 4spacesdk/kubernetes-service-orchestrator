<?php namespace App\Database\Migrations;

use App\Controllers\DatabaseServices;
use App\Controllers\Deployments;
use App\Controllers\DeploymentSteps;
use App\Controllers\Domains;
use App\Controllers\EmailServices;
use App\Controllers\Environments;
use App\Controllers\Home;
use App\Controllers\Jobby;
use App\Controllers\KeelHookQueueItems;
use App\Controllers\KeelHooks;
use App\Controllers\KeelPolicies;
use App\Controllers\Kubernetes;
use App\Controllers\Login;
use App\Controllers\MigrationJobs;
use App\Controllers\Settings;
use App\Controllers\Swagger;
use App\Controllers\Systems;
use App\Controllers\Users;
use App\Controllers\Workspaces;
use App\Entities\CronJob;
use App\Entities\User;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class Init extends Migration {

    public function up() {
        \AuthExtension\Migration\Setup::migrateUp();
        \RestExtension\Migration\Setup::migrateUp();

        Database::connect()->query("
            CREATE TABLE IF NOT EXISTS `ci_sessions` (
                    `id` varchar(128) NOT NULL,
                    `ip_address` varchar(45) NOT NULL,
                    `timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
                    `data` blob NOT NULL,
                    KEY `ci_sessions_timestamp` (`timestamp`)
            );");

        Database::connect()->query("truncate oauth_clients");

        // Get tls.domain
        $url = base_url();
        $parts = explode('/', $url);
        $url = implode('/', array_slice($parts, 0, 3));

        Database::connect()->query("insert into oauth_clients 
        values (
                \"webclient\", 
                \"\", 
                \"http://localhost:8951 $url\", 
                \"implicit\", 
                \"\", 
                0)
        ");
        Database::connect()->query("insert into oauth_clients 
        values (
                \"swagger\", 
                \"\", 
                \"http://localhost:8951 $url\", 
                \"implicit\", 
                \"\", 
                0)
        ");

        Database::connect()->query("truncate users");
        $user = new User();
        $user->first_name = 'Admin';
        $user->last_name = '';
        $user->username = 'admin@4spaces.dk';
        $user->password = password_hash('admin', PASSWORD_BCRYPT);
        $user->save();

        Table::init('deletions')
            ->create()
            ->createdUpdatedBy()
            ->timestamps();

        Table::init('cron_jobs')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_127)
            ->column('schedule', ColumnTypes::VARCHAR_127)
            ->column('command', ColumnTypes::VARCHAR_255)
            ->column('last_run', ColumnTypes::DATETIME)
            ->column('last_log', ColumnTypes::TEXT)
            ->column('duplicates', ColumnTypes::INT, 1);

        Table::init('workspaces')
            ->create()
            ->column('type', ColumnTypes::VARCHAR_63)
            ->column('namespace', ColumnTypes::VARCHAR_511)->addIndex('namespace')
            ->column('email_service_id', ColumnTypes::INT)->addIndex('email_service_id')
            ->column('domain_id', ColumnTypes::INT)->addIndex('domain_id')
            ->column('subdomain', ColumnTypes::VARCHAR_63)
            ->column('aliases', ColumnTypes::VARCHAR_63)
            ->column('database_service_id', ColumnTypes::INT)->addIndex('database_service_id')
            ->timestamps()
            ->softDelete();

        Table::init('deployments')
            ->create()
            ->column('workspace_id', ColumnTypes::INT)->addIndex('workspace_id')

            ->column('spec', ColumnTypes::VARCHAR_63)
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('namespace', ColumnTypes::VARCHAR_511)->addIndex('namespace')
            ->column('image', ColumnTypes::VARCHAR_511)->addIndex('image')
            ->column('version', ColumnTypes::VARCHAR_27)
            ->column('environment', ColumnTypes::VARCHAR_63)
            ->column('status', ColumnTypes::VARCHAR_27)
            ->column('last_updated', ColumnTypes::DATETIME)

            ->column('database_service_id', ColumnTypes::INT)->addIndex('database_service_id')
            ->column('database_name', ColumnTypes::VARCHAR_63)
            ->column('database_user', ColumnTypes::VARCHAR_63)
            ->column('database_pass', ColumnTypes::VARCHAR_63)

            ->column('domain_id', ColumnTypes::INT)->addIndex('domain_id')
            ->column('subdomain', ColumnTypes::VARCHAR_63)
            ->column('aliases', ColumnTypes::VARCHAR_63)

            ->column('keel_policy', ColumnTypes::VARCHAR_63)
            ->column('keel_auto_update', ColumnTypes::BOOL_0)

            ->column('cpu_limit', ColumnTypes::INT)
            ->column('cpu_request', ColumnTypes::INT)
            ->column('memory_limit', ColumnTypes::INT)
            ->column('memory_request', ColumnTypes::INT)
            ->column('replicas', ColumnTypes::INT)

            ->column('oauth_client_id', ColumnTypes::VARCHAR_127)
            ->column('oauth_client_secret', ColumnTypes::VARCHAR_127)

            ->column('last_migration_job_id', ColumnTypes::INT)->addIndex('last_migration_job_id')

            ->timestamps()
            ->softDelete();

        Table::init('environment_variables')
            ->create()
            ->column('deployment_id', ColumnTypes::INT)->addIndex('deployment_id')
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('value', ColumnTypes::VARCHAR_511)
            ->timestamps()
            ->softDelete();

        Table::init('email_services')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('host', ColumnTypes::VARCHAR_511)
            ->column('port', ColumnTypes::INT)
            ->column('user', ColumnTypes::VARCHAR_511)
            ->column('pass', ColumnTypes::VARCHAR_511)
            ->column('from', ColumnTypes::VARCHAR_511)
            ->timestamps()
            ->softDelete();

        Table::init('domains')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('certificate_name', ColumnTypes::VARCHAR_127)
            ->timestamps()
            ->softDelete();

        Table::init('database_services')
            ->create()
            ->column('name', ColumnTypes::VARCHAR_511)
            ->column('host', ColumnTypes::VARCHAR_511)
            ->column('port', ColumnTypes::INT)
            ->column('user', ColumnTypes::VARCHAR_511)
            ->column('pass', ColumnTypes::VARCHAR_511)
            ->timestamps()
            ->softDelete();

        Table::init('migration_jobs')
            ->create()
            ->column('deployment_id', ColumnTypes::INT)->addIndex('deployment_id')
            ->column('status', ColumnTypes::VARCHAR_63)
            ->column('started', ColumnTypes::DATETIME)
            ->column('ended', ColumnTypes::DATETIME)
            ->column('log', ColumnTypes::TEXT)
            ->timestamps();

        Table::init('keel_hook_queue_items')
            ->create()
            ->column('data', ColumnTypes::TEXT)
            ->column('log', ColumnTypes::TEXT)
            ->column('status', ColumnTypes::VARCHAR_63)
            ->timestamps();

        Table::init('workspaces')
            ->column('name_readable', ColumnTypes::VARCHAR_511)
            ->column('name_system', ColumnTypes::VARCHAR_511);

        Table::init('systems')
            ->create()
            ->timestamps()
            ->column('default_email_service_id', ColumnTypes::INT)
            ->column('default_database_service_id', ColumnTypes::INT);

        Table::init('zmq_events')
            ->create()
            ->column('event', ColumnTypes::VARCHAR_127)
            ->column('data', ColumnTypes::TEXT)
            ->column('identifier', ColumnTypes::VARCHAR_255)->addIndex('identifier')
            ->timestamps();


        ApiRoute::addResourceController(Users::class);
        ApiRoute::addResourceControllerGet(KeelPolicies::class);
        ApiRoute::addResourceControllerGet(Environments::class);
        ApiRoute::addResourceController(Domains::class);
        ApiRoute::addResourceController(DatabaseServices::class);
        ApiRoute::addResourceController(EmailServices::class);
        ApiRoute::addResourceController(Workspaces::class);
        ApiRoute::addResourceController(Deployments::class);
        ApiRoute::addResourceControllerGet(MigrationJobs::class);
        ApiRoute::addResourceControllerGet(KeelHookQueueItems::class);

        ApiRoute::quick('users/me', Users::class, 'me');
        ApiRoute::quick('/systems/default_email_service_id', Systems::class, 'updateDefaultEmailService', 'put');
        ApiRoute::quick('/systems/default_database_service_id', Systems::class, 'updateDefaultDatabaseService', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/ingress', Workspaces::class, 'updateIngress/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/status', Workspaces::class, 'getStatus/$1', 'get');
        ApiRoute::quick('/workspaces/([0-9]+)/requestSupportLogin', Workspaces::class, 'requestSupportLogin/$1', 'get');
        ApiRoute::quick('/kubernetes/namespaces/(.*)/pods', Kubernetes::class, 'getPods/$1', 'get');
        ApiRoute::quick('/kubernetes/namespaces/(.*)/pods/(.*)/logs', Kubernetes::class, 'getLogs/$1/$2', 'get');
        ApiRoute::quick('/kubernetes/namespaces/(.*)/pods/(.*)/logs/watch', Kubernetes::class, 'watchLogs/$1/$2', 'put');
        ApiRoute::quick('/keel-hook-queue-items/([0-9]+)/rerun', KeelHookQueueItems::class, 'rerun/$1', 'post');
        ApiRoute::quick('/workspaces/create', Workspaces::class, 'create', 'post');
        ApiRoute::quick('/deployments/([0-9]+)/migration-jobs', Deployments::class, 'getMigrationJobs/$1', 'get');
        ApiRoute::quick('/deployments/([0-9]+)/environment-variables', Deployments::class, 'updateEnvironmentVariables/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/migration-jobs', Workspaces::class, 'getMigrationJobs/$1', 'get');
        ApiRoute::quick('/deployment-steps/(.*)/preview', DeploymentSteps::class, 'getPreview/$1', 'get');
        ApiRoute::quick('/deployment-steps/(.*)/status', DeploymentSteps::class, 'getStatus/$1', 'get');
        ApiRoute::quick('/deployment-steps/(.*)/deploy', DeploymentSteps::class, 'deploy/$1', 'put');
        ApiRoute::quick('/deployment-steps/(.*)/terminate', DeploymentSteps::class, 'terminate/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/status', Deployments::class, 'getStatus/$1', 'get');
        ApiRoute::quick('/deployment-steps/(.*)/kubernetes-events', DeploymentSteps::class, 'getKubernetesEvents/$1', 'get');
        ApiRoute::quick('/deployment-steps/(.*)/kubernetes-status', DeploymentSteps::class, 'getKubernetesStatus/$1', 'get');
        ApiRoute::quick('/workspaces/([0-9]+)/name', Workspaces::class, 'updateName/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/emailServiceId', Workspaces::class, 'updateEmailServiceId/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/databaseServiceId', Workspaces::class, 'updateDatabaseServiceId/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/deploy', Workspaces::class, 'deploy/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/terminate', Workspaces::class, 'terminate/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/resourceManagement', Deployments::class, 'updateResourceManagemnet/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/updateManagement', Deployments::class, 'updateUpdateManagement/$1', 'put');
        ApiRoute::quick('/domains/([0-9]+)/certificate/apply', Domains::class, 'applyCertificate/$1', 'put');
        ApiRoute::quick('/domains/([0-9]+)/certificate/events', Domains::class, 'getCertificateEvents/$1', 'get');
        ApiRoute::quick('/domains/([0-9]+)/certificate/status', Domains::class, 'getCertificateStatus/$1', 'get');
        ApiRoute::quick('/deployments/create', Deployments::class, 'create', 'post');
        ApiRoute::quick('/deployments/([0-9]+)/version', Deployments::class, 'updateVersion/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/environment', Deployments::class, 'updateEnvironment/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/databaseServiceId', Deployments::class, 'updateDatabaseServiceId/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/ingress', Deployments::class, 'updateIngress/$1', 'put');

        ApiRoute::public('/migration-jobs/([0-9]+)/started', MigrationJobs::class, 'setStarted/$1', 'put');
        ApiRoute::public('/migration-jobs/([0-9]+)/ended', MigrationJobs::class, 'setEnded/$1', 'put');
        ApiRoute::public('/KeelHooks', KeelHooks::class, 'index', 'post');
        ApiRoute::public('/jobby', Jobby::class, 'index');
        ApiRoute::public('/jobby/run/([0-9]+)', Jobby::class, 'run/$1');
        ApiRoute::public('settings', Settings::class, 'index');
        ApiRoute::public('login', Login::class, 'index');
        ApiRoute::public('login', Login::class, 'index', 'post');
        ApiRoute::public('login/success', Login::class, 'success');
        ApiRoute::public('home', Home::class, 'index');
        ApiRoute::public('/swagger', Swagger::class, '');
        ApiRoute::public('/swagger/openapi', Swagger::class, 'openapi');



        $job = new CronJob();
        $job->find(\CronJobIds::CleanupGoogleContainerRegistry);
        $job->schedule = '00 03 * * *';
        $job->command = 'app:cleanup_gcr';
        $job->duplicates = 1;
        $job->save();
        $job = new CronJob();
        $job->find(\CronJobIds::RunKeelHooks);
        $job->name = 'Keel Hooks';
        $job->schedule = '* * * * *';
        $job->command = 'app:keel_hooks';
        $job->duplicates = 30;
        $job->save();
    }

    public function down() {
        \AuthExtension\Migration\Setup::migrateDown();
    }

}
