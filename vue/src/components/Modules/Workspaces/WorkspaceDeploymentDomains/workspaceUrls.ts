import type {Deployment, Workspace} from "@/core/services/Deploy/models";

export interface WorkspaceUrl {
    deployment: Deployment;
    url: string;
}

/**
 * Where a workspace answers: the external url of each deployment that has external access,
 * sorted. Read from the workspace's deployments, so they must be included with it.
 */
export function workspaceUrls(workspace: Workspace): WorkspaceUrl[] {
    return (workspace.deployments ?? [])
        .filter(deployment => deployment.deployment_specification?.enable_external_access ?? false)
        .map(deployment => ({
            deployment: deployment,
            url: deployment.url_external ?? 'missing url',
        }))
        .sort((a, b) => a.url.localeCompare(b.url));
}

/** Every url here is https, and the scheme costs width where there is little of it. */
export function withoutScheme(url: string): string {
    return url.replace(/^https?:\/\//, '');
}
