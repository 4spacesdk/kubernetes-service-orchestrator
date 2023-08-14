export class Events {
    private static prefix = 'events';

    private static Generate(name: string): string {
        name = name
            .toLocaleLowerCase()
            .replace(new RegExp('-', 'g'), '_');
        return `${Events.prefix}.${name}`;
    }

    public static Deployment_Changed_Status(deploymentId: number): string {
        return Events.Generate(`deployment.${deploymentId}.changed.status`);
    }

    public static Workspace_Changed_Status(workspace: number): string {
        return Events.Generate(`workspace.${workspace}.changed.status`);
    }

    public static MigrationJob_Created(): string {
        return Events.Generate(`migration-job.created`);
    }

    public static MigrationJob_Changed_Status(migrationJobId: number): string {
        return Events.Generate(`migration-job.${migrationJobId}.changed.status`);
    }

    public static KeelHookQueueItem_Created(): string {
        return Events.Generate(`keel-hook-queue-item.created`);
    }

    public static KeelHookQueueItem_Deleted(): string {
        return Events.Generate(`keel-hook-queue-item.deleted`);
    }

    public static KeelHookQueueItem_Changed_Status(keelHookQueueItemId: number): string {
        return Events.Generate(`keel-hook-queue-item.${keelHookQueueItemId}.changed.status`);
    }

    public static KubernetesPod_Logs_Watch(pod: string): string {
        return Events.Generate(`kubernetes.pod.${pod}.logs.watch`);
    }

}
