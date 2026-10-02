import { onMounted, onUnmounted, reactive, watch } from "vue";
import { HealthStatusTypes } from "@/constants";
import { Api } from "@/core/services/Deploy/Api";
import type { UsersAutoUpdatesBadgeResponse } from "@/core/services/Deploy/Api";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import type { PushSubscription } from "@/services/Push/PushSubscription";
import { showFaviconCount } from "@/components/Shell/Menu/faviconBadge";

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
    /**
     * Auto updates approved on their own since the user last opened Updates - news until they
     * do, unlike the waiting ones, which stay until somebody approves them.
     */
    unseenAutoUpdates: 0,
});

/** The Updates number, in the menu and on the browser tab's icon. */
const autoUpdatesCount = () => counts.pendingAutoUpdates + counts.unseenAutoUpdates;

let users = 0;
let subscriptions: PushSubscription[] = [];
let stopFavicon: (() => void) | null = null;

/** A category's own number, by its identifier. */
export function categoryBadge(identifier: string): MenuBadge | null {
    switch (identifier) {
        case "sites":
            return badge(counts.degradedWorkspaces, "error");
        case "auto-updates":
            return badge(autoUpdatesCount(), "secondary");
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
        stopFavicon = watch(autoUpdatesCount, showFaviconCount, { immediate: true });
    });

    onUnmounted(() => {
        if (--users > 0) {
            return;
        }
        subscriptions.forEach((subscription) => subscription.unsubscribe());
        subscriptions = [];
        stopFavicon?.();
        showFaviconCount(0);
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

/** Counted by the server, where the time the user last looked and the approvals are in the same terms. */
function countAutoUpdates() {
    Api.users().autoUpdatesBadgeGet().find((badges) => setAutoUpdates(badges[0]));
}

function setAutoUpdates(badge?: UsersAutoUpdatesBadgeResponse) {
    counts.pendingAutoUpdates = badge?.waiting ?? 0;
    counts.unseenAutoUpdates = badge?.approved_on_their_own ?? 0;
}

/**
 * For the Updates page: what was approved on its own is seen once the page is open - and so is
 * what arrives while it is. What waits for approval stays in the badge.
 */
export function useAutoUpdatesSeen() {
    let subscriptions: PushSubscription[] = [];
    const markSeen = () => Api.users().autoUpdatesSeenPut().save(null, (badge) => setAutoUpdates(badge));

    onMounted(() => {
        markSeen();
        subscriptions = [
            PushService.subscribe(Events.AutoUpdate_Created(), () => markSeen()),
            PushService.subscribe(Events.AutoUpdate_Approved(), () => markSeen()),
        ];
    });

    onUnmounted(() => {
        subscriptions.forEach((subscription) => subscription.unsubscribe());
        subscriptions = [];
    });
}
