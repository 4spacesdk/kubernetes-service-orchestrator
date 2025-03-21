<?php

class CronJobIds {
    const int
        CleanupGoogleContainerRegistry = 1, // Removed in v1.5.0
        RunKeelHooks = 2, // Removed in v0.1.18
        PullContainerRegistries = 3,
        CheckCertificateExpiry = 4
    ;
}

class Environments {

    const string
        Development = 'development',
        Production = 'production';

    public static function All(): array {
        return [
            self::Development, self::Production,
        ];
    }
}

class DeploymentStatusTypes {
    const string Draft = 'draft';
    const string Deploying = 'deploying';
    const string Active = 'active';
    const string Error = 'error';
}

class KeelHookStatusTypes {
    const string New = 'new';
    const string Running = 'running';
    const string Finished = 'finished';
    const string Error = 'error';
}

class MigrationJobStatusTypes {
    const string
        Deploying = 'deploying',
        Started = 'started',
        Completed = 'completed',
        Failed_LogVerification = 'failed-log-verification',
        Failed_PostCommands = 'failed-post-commands';
}

class WebHookTypes {
    const string
        Workspace_Created = 'workspace-created',
        Workspace_Updated = 'workspace-updated',
        Workspace_Deleted = 'workspace-deleted',
        Workspace_Deployed = 'workspace-deployed',
        Workspace_Terminated = 'workspace-terminated',
        Deployment_Deployed = 'deployment-deployed',
        Deployment_Terminated = 'deployment-terminated';

    public static function All(): array {
        return [
            self::Workspace_Created,
            self::Workspace_Updated,
            self::Workspace_Deleted,
            self::Workspace_Deployed,
            self::Workspace_Terminated,
            self::Deployment_Deployed,
            self::Deployment_Terminated,
        ];
    }
}

class DatabaseDrivers {
    const string
        MySQL = 'mysql',
        MSSQL = 'mssql';
}

class ContainerImageTagPolicies {
    const string
        MatchDeployment = 'match-deployment',
        Static = 'static',
        Default = 'default';
}

class ImagePullPolicies {
    const string
        IfNotPresent = 'IfNotPresent',
        Always = 'Always',
        Never = 'Never';
}

class MigrationVerificationTypes {
    const string
        EndsWith = 'ends-with',
        Regex = 'regex';
}

class ContainerRegistries {
    const string
        ArtifactContainerRegistry = 'artifact-container-registry',
        AzureContainerRegistry = 'azure-container-registry';
}

class CommitIdentificationMethods {
    const string
        EnvironmentVariable = 'environment-variable';
}

class VersionControlProviders {
    const string
        GitHub = 'github';
}

class PostUpdateActionTypes {
    const string
        Podio_AddComment = 'podio-add-comment',
        Podio_FieldUpdate = 'podio-field-update'
    ;
}

class PostUpdateActionConditionTypes {
    const string
        PodioFieldEquals = 'podio-field-equals'
    ;
}

class CronJobConcurrencyPolicies {
    const string
        Allow = 'Allow',
        Forbid = 'Forbid',
        Replace = 'Replace'
    ;
}

class CronJobRestartPolicies {
    const string
        Always = 'Always',
        OnFailure = 'OnFailure',
        Never = 'Never'
    ;
}

class WorkloadTypes {
    const string
        Deployment = 'deployment',
        KNativeService = 'knative-service',
        DaemonSet = 'daemon-set',
        CustomResource = 'custom-resource'
    ;
}

class NetworkTypes {
    const string
        NginxIngress = 'nginx-ingress',
        Istio = 'istio',
        Contour = 'contour'
    ;
}


/*
 | --------------------------------------------------------------------
 | App Namespace
 | --------------------------------------------------------------------
 |
 | This defines the default Namespace that is used throughout
 | CodeIgniter to refer to the Application directory. Change
 | this constant to change the namespace that all application
 | classes should use.
 |
 | NOTE: changing this will require manually modifying the
 | existing namespaces of App\* namespaced-classes.
 */
defined('APP_NAMESPACE') || define('APP_NAMESPACE', 'App');

/*
 | --------------------------------------------------------------------------
 | Composer Path
 | --------------------------------------------------------------------------
 |
 | The path that Composer's autoload file is expected to live. By default,
 | the vendor folder is in the Root directory, but you can customize that here.
 */
defined('COMPOSER_PATH') || define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');

/*
 |--------------------------------------------------------------------------
 | Timing Constants
 |--------------------------------------------------------------------------
 |
 | Provide simple ways to work with the myriad of PHP functions that
 | require information to be in seconds.
 */
defined('SECOND') || define('SECOND', 1);
defined('MINUTE') || define('MINUTE', 60);
defined('HOUR') || define('HOUR', 3600);
defined('DAY') || define('DAY', 86400);
defined('WEEK') || define('WEEK', 604800);
defined('MONTH') || define('MONTH', 2_592_000);
defined('YEAR') || define('YEAR', 31_536_000);
defined('DECADE') || define('DECADE', 315_360_000);

/*
 | --------------------------------------------------------------------------
 | Exit Status Codes
 | --------------------------------------------------------------------------
 |
 | Used to indicate the conditions under which the script is exit()ing.
 | While there is no universal standard for error codes, there are some
 | broad conventions.  Three such conventions are mentioned below, for
 | those who wish to make use of them.  The CodeIgniter defaults were
 | chosen for the least overlap with these conventions, while still
 | leaving room for others to be defined in future versions and user
 | applications.
 |
 | The three main conventions used for determining exit status codes
 | are as follows:
 |
 |    Standard C/C++ Library (stdlibc):
 |       http://www.gnu.org/software/libc/manual/html_node/Exit-Status.html
 |       (This link also contains other GNU-specific conventions)
 |    BSD sysexits.h:
 |       http://www.gsp.com/cgi-bin/man.cgi?section=3&topic=sysexits
 |    Bash scripting:
 |       http://tldp.org/LDP/abs/html/exitcodes.html
 |
 */
defined('EXIT_SUCCESS') || define('EXIT_SUCCESS', 0);        // no errors
defined('EXIT_ERROR') || define('EXIT_ERROR', 1);          // generic error
defined('EXIT_CONFIG') || define('EXIT_CONFIG', 3);         // configuration error
defined('EXIT_UNKNOWN_FILE') || define('EXIT_UNKNOWN_FILE', 4);   // file not found
defined('EXIT_UNKNOWN_CLASS') || define('EXIT_UNKNOWN_CLASS', 5);  // unknown class
defined('EXIT_UNKNOWN_METHOD') || define('EXIT_UNKNOWN_METHOD', 6); // unknown class member
defined('EXIT_USER_INPUT') || define('EXIT_USER_INPUT', 7);     // invalid user input
defined('EXIT_DATABASE') || define('EXIT_DATABASE', 8);       // database error
defined('EXIT__AUTO_MIN') || define('EXIT__AUTO_MIN', 9);      // lowest automatically-assigned error code
defined('EXIT__AUTO_MAX') || define('EXIT__AUTO_MAX', 125);    // highest automatically-assigned error code

/**
 * @deprecated Use \CodeIgniter\Events\Events::PRIORITY_LOW instead.
 */
define('EVENT_PRIORITY_LOW', 200);

/**
 * @deprecated Use \CodeIgniter\Events\Events::PRIORITY_NORMAL instead.
 */
define('EVENT_PRIORITY_NORMAL', 100);

/**
 * @deprecated Use \CodeIgniter\Events\Events::PRIORITY_HIGH instead.
 */
define('EVENT_PRIORITY_HIGH', 10);
