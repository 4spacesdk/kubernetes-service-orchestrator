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
        CleanupQueue = 10,
        CleanupApiLogs = 11,
        CleanupOAuthTokens = 12,
        CleanupAuditEvents = 13,
        CheckHealth = 14
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

/**
 * Whether kso's resources for a deployment are in the cluster - a sync status, in Argo CD's
 * sense. Not whether the workload is doing well: that is `HealthStatusTypes`, beside it.
 *
 * These were Active and Deploying, with an Error nothing ever set. "Deploying" was what a
 * deployment said when any of its resources was missing - also for good, after somebody deleted
 * one with kubectl - and Active promised more than it measured.
 */
class DeploymentStatusTypes {
    /** Not complete enough to be deployed. */
    const string Draft = 'draft';
    /** Some of its resources are not in the cluster - while a deploy runs, or after one was removed. */
    const string OutOfSync = 'out_of_sync';
    /** Every one of its resources is in the cluster. */
    const string Synced = 'synced';
    /** Switched off, and left off until it is deployed again. */
    const string Inactive = 'inactive';
}

class WorkspaceStatusTypes {
    const string Draft = 'draft';
    const string OutOfSync = 'out_of_sync';
    const string Synced = 'synced';
    const string Inactive = 'inactive';

    /**
     * Set by a person and left alone by `Workspace::checkStatus()`, unlike the four above,
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

/**
 * Whether a deployment's workload is doing well right now - a second axis beside its status,
 * which only says whether kso's resources are in the cluster. Argo CD's six, unchanged, so the
 * words mean what they mean everywhere else. See `Libraries/Health`.
 *
 * Health never writes the status: auto update picks deployments by status, and a crash loop
 * must not change what it does.
 */
class HealthStatusTypes {
    const string
        Healthy = 'healthy',
        Progressing = 'progressing',
        Degraded = 'degraded',
        Suspended = 'suspended',
        Missing = 'missing',
        Unknown = 'unknown'
    ;

    /**
     * How bad each one is - what a workspace takes the worst of, and what the lists sort by.
     * Suspended is below Healthy: a paused deployment is the one thing nobody needs to look at.
     */
    private const array Severities = [
        self::Suspended => 0,
        self::Healthy => 1,
        self::Unknown => 2,
        self::Progressing => 3,
        self::Missing => 4,
        self::Degraded => 5,
    ];

    public static function Severity(?string $health): ?int {
        return $health === null ? null : (self::Severities[$health] ?? self::Severities[self::Unknown]);
    }

    /**
     * The worst of them, ignoring the nulls - a deployment with no health is one there is
     * nothing to say about, not one that is doing badly. Null when there are none.
     */
    public static function Worst(?string ...$healths): ?string {
        $worst = null;
        foreach ($healths as $health) {
            if ($health !== null && ($worst === null || self::Severity($health) > self::Severity($worst))) {
                $worst = $health;
            }
        }
        return $worst;
    }

    /** The two a webhook is sent for even when nothing has been sent before. */
    public static function IsBad(?string $health): bool {
        return $health === self::Degraded || $health === self::Missing;
    }
}

class WebHookTypes {
    const string
        Workspace_Created = 'workspace-created',
        Workspace_Updated = 'workspace-updated',
        Workspace_Deleted = 'workspace-deleted',
        Workspace_Deployed = 'workspace-deployed',
        Workspace_Terminated = 'workspace-terminated',
        Deployment_Deployed = 'deployment-deployed',
        Deployment_Terminated = 'deployment-terminated',
        Deployment_Health_Changed = 'deployment-health-changed';

    public static function All(): array {
        return [
            self::Workspace_Created,
            self::Workspace_Updated,
            self::Workspace_Deleted,
            self::Workspace_Deployed,
            self::Workspace_Terminated,
            self::Deployment_Deployed,
            self::Deployment_Terminated,
            self::Deployment_Health_Changed,
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
