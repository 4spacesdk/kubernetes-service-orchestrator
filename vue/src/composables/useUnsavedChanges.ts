import { onMounted, onUnmounted } from "vue";
import { onBeforeRouteLeave, onBeforeRouteUpdate } from "vue-router";
import bus from "@/plugins/bus";

/**
 * Asks before leaving a section of a page with changes that were not saved. A dialog could
 * not be left without Close or Save; a page can, by any link, so the question moves here.
 *
 * Changed means different from the last `markSaved()`, compared as JSON, so rows edited in a
 * dialog of their own count too:
 *
 *     const { markSaved } = useUnsavedChanges(() => rows.value);
 *     load().then(() => markSaved());
 *     save().then(() => markSaved());
 */
export function useUnsavedChanges(state: () => unknown) {
    let saved: string | null = null;

    const isChanged = () => saved !== null && JSON.stringify(state()) !== saved;

    function markSaved() {
        saved = JSON.stringify(state());
    }

    function ask(): Promise<boolean> {
        if (!isChanged()) {
            return Promise.resolve(true);
        }
        return new Promise(resolve => bus.emit("confirm", {
            body: "Leave without saving your changes?",
            responseCallback: (confirmed: boolean) => resolve(confirmed),
        }));
    }

    // A section is swapped within the same route, so both guards are needed.
    onBeforeRouteLeave(() => ask());
    onBeforeRouteUpdate((to, from) => to.path == from.path || ask());

    function onBeforeUnload(event: BeforeUnloadEvent) {
        if (isChanged()) {
            event.preventDefault();
        }
    }
    onMounted(() => window.addEventListener("beforeunload", onBeforeUnload));
    onUnmounted(() => window.removeEventListener("beforeunload", onBeforeUnload));

    return { markSaved, isChanged };
}
