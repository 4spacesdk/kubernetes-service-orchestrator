<?php namespace App\Libraries\Push;

class Events {

    private static $prefix = 'events';

    private static function Generate(string $name): string {
        $name = str_replace('-', '_', strtolower($name));
        return Events::$prefix.'.'.$name;
    }

    public static function Deployment_Changed_Status(int $deploymentId): string {
        return Events::Generate("deployment.$deploymentId.changed.status");
    }

    public static function Workspace_Changed_Status(int $workspace): string {
        return Events::Generate("workspace.$workspace.changed.status");
    }

    /**
     * The deployment's health or its reason changed - see `Libraries/Health`.
     */
    public static function Deployment_Changed_Health(int $deploymentId): string {
        return Events::Generate("deployment.$deploymentId.changed.health");
    }

    public static function Workspace_Changed_Health(int $workspaceId): string {
        return Events::Generate("workspace.$workspaceId.changed.health");
    }

    /**
     * Some deployment's health changed. What the menu recounts its Degraded badge on, so it
     * needs one channel rather than one per deployment.
     */
    public static function Deployments_Changed_Health(): string {
        return Events::Generate("deployments.changed.health");
    }

    /**
     * A new health has held long enough to be worth telling somebody - the webhook.
     */
    public static function Deployment_Health_Settled(): string {
        return Events::Generate("deployment.health.settled");
    }

    /**
     * Look at one deployment again shortly, while it rolls out - see `HealthCheck::Follow()`.
     */
    public static function Deployment_Health_Follow(): string {
        return Events::Generate("deployment.health.follow");
    }

    /**
     * A scan of one of the image's tags was queued, started, or finished.
     */
    public static function ContainerImage_Scans_Changed(int $containerImageId): string {
        return Events::Generate("container-image.$containerImageId.scans.changed");
    }

    public static function MigrationJob_Created(): string {
        return Events::Generate("migration-job.created");
    }

    public static function MigrationJob_Changed_Status(int $migrationJobId): string {
        return Events::Generate("migration-job.$migrationJobId.changed.status");
    }

    public static function KubernetesPod_Logs_Watch(string $pod, string $container): string {
        return Events::Generate("kubernetes.pod.{$pod}.containers.{$container}.logs.watch");
    }

    public static function Workspace_Created(): string {
        return Events::Generate("workspace.created");
    }

    public static function Workspace_Updated(): string {
        return Events::Generate("workspace.updated");
    }

    public static function Workspace_Deleted(): string {
        return Events::Generate("workspace.deleted");
    }

    public static function Workspace_Deployed(): string {
        return Events::Generate("workspace.deployed");
    }

    public static function Workspace_Terminated(): string {
        return Events::Generate("workspace.terminated");
    }

    public static function Deployment_Deployed(): string {
        return Events::Generate("deployment.deployed");
    }

    public static function Deployment_Terminated(): string {
        return Events::Generate("deployment.terminated");
    }

    public static function AutoUpdate_Created(): string {
        return Events::Generate("auto-update-created");
    }

    public static function AutoUpdate_Deleted(): string {
        return Events::Generate("auto-update-deleted");
    }

    public static function AutoUpdate_Approved(): string {
        return Events::Generate("auto-update-approved");
    }

    public static function AutoUpdate_RolledOut(): string {
        return Events::Generate("auto-update-rolled-out");
    }

}
