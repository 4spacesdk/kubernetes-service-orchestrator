<?php namespace App\Libraries\ZMQ;

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

    public static function MigrationJob_Created(): string {
        return Events::Generate("migration-job.created");
    }

    public static function MigrationJob_Changed_Status(int $migrationJobId): string {
        return Events::Generate("migration-job.$migrationJobId.changed.status");
    }

    public static function KeelHookQueueItem_Created(): string {
        return Events::Generate("keel-hook-queue-item.created");
    }

    public static function KeelHookQueueItem_Deleted(): string {
        return Events::Generate("keel-hook-queue-item.deleted");
    }

    public static function KeelHookQueueItem_Changed_Status(int $keelHookQueueItemId): string {
        return Events::Generate("keel-hook-queue-item.$keelHookQueueItemId.changed.status");
    }

    public static function KubernetesPod_Logs_Watch(string $pod): string {
        return Events::Generate("kubernetes.pod.{$pod}.logs.watch");
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

}
