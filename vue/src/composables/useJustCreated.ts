import { ref } from "vue";

/**
 * The workspaces and deployments created in this tab, by id. Their lists show them whatever the
 * status and health filters say: a new one is a Draft with no health, and the default filters
 * are Out of sync and Synced - so it was gone from the list it was just created on until the
 * filters were cleared. Kept until the page is reloaded, so it is still there on the way back
 * from its own page.
 */
const workspaceIds = ref<number[]>([]);
const deploymentIds = ref<number[]>([]);

export function useJustCreated() {
    return { workspaceIds, deploymentIds };
}

/**
 * Of `ids`, the ones not in `items` - the ones a list fetches on their own and puts first.
 */
export function missingFrom(ids: number[], items: { id?: number }[]): number[] {
    return ids.filter(id => !items.some(item => item.id == id));
}
