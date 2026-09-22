<?php

/**
 * Constants that describe kso's own domain: the statuses a deployment moves through, the
 * kinds of workload it can be, the registries it talks to, and so on.
 *
 * They live here rather than in Config/Constants.php, which belongs to CodeIgniter and is
 * replaced on every framework upgrade. Keeping them apart means a upgrade never has to be
 * merged around them. Config/Constants.php requires this file.
 */

class CronJobIds {
    const int
        CleanupGoogleContainerRegistry = 1, // Removed in v1.5.0
        RunKeelHooks = 2, // Removed in v0.1.18
        PullContainerRegistries = 3,
        CheckCertificateExpiry = 4,
        CheckKNativeMinScaleSchedules = 5,
        CleanupZmqEvents = 6, // Removed in v1.9.0
        CleanupSignInAttempts = 7,
        ScanContainerImages = 8,
        ScanQueuedContainerImages = 9,
        CleanupQueue = 10
    ;
}

class ContainerImageScanStatuses {

    const string
        Queued = 'queued',
        Scanning = 'scanning',
        Scanned = 'scanned',
        Failed = 'failed';

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
    const string Inactive = 'inactive';
    const string Error = 'error';
}

class WorkspaceStatusTypes {
    const string Draft = 'draft';
    const string Deploying = 'deploying';
    const string Active = 'active';
    const string Inactive = 'inactive';
    const string Error = 'error';

    /**
     * Set by a person and left alone by `Workspace::checkStatus()`, unlike the five above,
     * which are recomputed from the deployments every time anything happens to one.
     */
    const string Paused = 'paused';
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
        AzureContainerRegistry = 'azure-container-registry',
        Harbor = 'harbor';
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
        Contour = 'contour',
        GatewayApi = 'gateway-api'
    ;
}

/**
 * The hosting provider is free text, so an installation can name a provider we do not know about.
 * These are the well known ones. Only Gke changes behavior today by enabling the HealthCheckPolicy step.
 */
class HostingProviders {
    const string
        Gke = 'gke',
        Eks = 'eks',
        Aks = 'aks',
        DigitalOcean = 'digitalocean',
        Openshift = 'openshift',
        SelfHosted = 'self-hosted'
    ;
}

class HealthCheckTypes {
    const string
        Http = 'http',
        Tcp = 'tcp'
    ;
}

class DeploymentAnnotationLevels {
    const string
        Deployment = 'deployment',
        Pod = 'pod'
    ;
}
