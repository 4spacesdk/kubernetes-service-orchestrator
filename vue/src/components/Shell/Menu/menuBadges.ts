import { onMounted, onUnmounted, reactive } from "vue";
import { HealthStatusTypes } from "@/constants";
import { Api } from "@/core/services/Deploy/Api";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import type { PushSubscription } from "@/services/Push/PushSubscription";

export interface MenuBadge {
    count: number;
    color: string;
}

/**
 * The numbers on the menu, counted once and shared: the side menu, the bottom menu and a
 * category's overview show the same, kept up to date by the same events.
 */
const counts = reactive({
    /** Degraded workspaces, on Sites - which everybody sees. */
    degradedWorkspaces: 0,
    /** The same by project id, `none` for those in no project. */
    degradedWorkspacesByProject: {} as Record<string, number>,
    /** Degraded deployments, on Deployments under Setup. */
    degradedDeployments: 0,
    /** Auto updates waiting for approval. */
    pendingAutoUpdates: 0,
});

let users = 0;
let subscriptions: PushSubscription[] = [];

/** A category's own number, by its identifier. */
export function categoryBadge(identifier: string): MenuBadge | null {
    switch (identifier) {
        case "sites":
            return badge(counts.degradedWorkspaces, "error");
        case "auto-updates":
            return badge(counts.pendingAutoUpdates, "secondary");
    }
    return null;
}

/** A page's number, by its url. */
export function itemBadge(url: string): MenuBadge | null {
    switch (url) {
        case "/setup/deployments":
            return badge(counts.degradedDeployments, "error");
        case "/workspaces/all":
            return badge(counts.degradedWorkspaces, "error");
    }
    const project = url.match(/^\/workspaces\/projects\/(\d+|none)$/);
    if (project) {
        return badge(counts.degradedWorkspacesByProject[project[1]] ?? 0, "error");
    }
    return null;
}

/**
 * Keeps the numbers counted while the calling component is mounted. The first to mount counts
 * and subscribes, the last to go unsubscribes.
 */
export function useMenuBadges() {
    onMounted(() => {
        if (users++ > 0) {
            return;
        }
        subscriptions = [
            PushService.subscribe(Events.AutoUpdate_Created(), () => countAutoUpdates()),
            PushService.subscribe(Events.AutoUpdate_Approved(), () => countAutoUpdates()),
            PushService.subscribe(Events.AutoUpdate_Deleted(), () => countAutoUpdates()),
            PushService.subscribe(Events.Deployments_Changed_Health(), () => countDegraded()),
        ];
        countAutoUpdates();
        countDegraded();
    });

    onUnmounted(() => {
        if (--users > 0) {
            return;
        }
        subscriptions.forEach((subscription) => subscription.unsubscribe());
        subscriptions = [];
    });
}

function badge(count: number, color: string): MenuBadge | null {
    return count > 0 ? { count, color } : null;
}

/** Recounted when any deployment's health changes. */
function countDegraded() {
    Api.workspaces()
        .get()
        .where("health", HealthStatusTypes.Degraded)
        .find((workspaces) => {
            const byProject: Record<string, number> = {};
            workspaces.forEach((workspace) => {
                const key = workspace.project_id ? String(workspace.project_id) : "none";
                byProject[key] = (byProject[key] ?? 0) + 1;
            });
            counts.degradedWorkspaces = workspaces.length;
            counts.degradedWorkspacesByProject = byProject;
        });

    Api.deployments()
        .get()
        .where("health", HealthStatusTypes.Degraded)
        .count((value) => (counts.degradedDeployments = value));
}

function countAutoUpdates() {
    Api.autoUpdates()
        .get()
        .where("is_approved", false)
        .count((value) => (counts.pendingAutoUpdates = value));
}
