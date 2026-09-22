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

    public static Deployment_Changed_Health(deploymentId: number): string {
        return Events.Generate(`deployment.${deploymentId}.changed.health`);
    }

    public static Workspace_Changed_Health(workspaceId: number): string {
        return Events.Generate(`workspace.${workspaceId}.changed.health`);
    }

    /** Some deployment's health changed - one channel for the menu's count. */
    public static Deployments_Changed_Health(): string {
        return Events.Generate(`deployments.changed.health`);
    }

    public static ContainerImage_Scans_Changed(containerImageId: number): string {
        return Events.Generate(`container-image.${containerImageId}.scans.changed`);
    }

    public static MigrationJob_Created(): string {
        return Events.Generate(`migration-job.created`);
    }

    public static MigrationJob_Changed_Status(migrationJobId: number): string {
        return Events.Generate(`migration-job.${migrationJobId}.changed.status`);
    }

    /** Every pod of one deployment, followed together. */
    public static Deployment_Logs_Watch(deploymentId: number): string {
        return Events.Generate(`deployment.${deploymentId}.logs.watch`);
    }

    public static KubernetesPod_Logs_Watch(pod: string, container: string): string {
        return Events.Generate(`kubernetes.pod.${pod}.containers.${container}.logs.watch`);
    }

    public static AutoUpdate_Created(): string {
        return Events.Generate(`auto-update-created`);
    }

    public static AutoUpdate_Deleted(): string {
        return Events.Generate(`auto-update-deleted`);
    }

    public static AutoUpdate_Approved(): string {
        return Events.Generate(`auto-update-approved`);
    }

    public static AutoUpdate_RolledOut(): string {
        return Events.Generate(`auto-update-rolled-out`);
    }

}
