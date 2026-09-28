import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { onBeforeRouteLeave, onBeforeRouteUpdate } from "vue-router";
import bus from "@/plugins/bus";

interface SavingApi<T> {
    setErrorHandler(handler: (response: any) => boolean): unknown;
    save(data: any, next?: (value: T) => void): unknown;
}

export type AutoSaveStatus = "idle" | "waiting" | "saving" | "saved" | "invalid" | "error";

/** What a section's header shows instead of its Save button. */
export interface AutoSaveState {
    status: AutoSaveStatus;
    /** Why it is not saved, for `invalid` and `error`. */
    message: string | null;
    retry: () => void;
}

export interface AutoSaveOptions<T> {
    /** What is saved. A change to it is what starts a save, compared as JSON. */
    state: () => unknown;
    /**
     * Why the state cannot be saved as it is, or null when it can. A section waits for a value
     * the server would refuse rather than sending it: Update Management turned on before its
     * pattern is typed is not an error, it is half a change.
     */
    validate?: () => string | null;
    /** The request for the current state. */
    request: () => SavingApi<T>;
    /** The body, for a request that takes one - a list of labels. */
    data?: () => unknown;
    onSaved?: (saved: T) => void;
    /** Quiet time before a change is sent: a choice is sent at once, typing when it pauses. */
    delay?: number;
}

/**
 * A settings section that saves as it goes, instead of waiting for Save.
 *
 * One save at a time, and the last change wins: a change made while one is on its way is sent
 * when it comes back, so two answers arriving out of order cannot store the older value. A
 * refusal stays on screen with its reason and Retry, and the change counts as unsaved - leaving
 * asks, as it did before. Leaving with a change not sent yet sends it first.
 *
 *     const { markLoaded, autoSave, saveNow } = useAutoSave({
 *         state: () => value.value,
 *         request: () => Api.x().updatePutById(id).value(value.value!),
 *         onSaved: saved => bus.emit("xSaved", saved),
 *     });
 *     load().then(() => markLoaded());
 *
 * and `:auto-save="autoSave"` on the `page-section`. `saveNow()` sends what is pending and
 * answers whether it is stored - for whoever needs to know before moving on.
 */
export function useAutoSave<T>(options: AutoSaveOptions<T>) {
    const delay = options.delay ?? 800;

    const status = ref<AutoSaveStatus>("idle");
    const message = ref<string | null>(null);

    /** The state as last stored, as JSON; null until it is loaded. */
    let stored: string | null = null;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let inFlight: Promise<boolean> | null = null;

    const current = () => JSON.stringify(options.state());
    const isChanged = () => stored !== null && current() !== stored;

    /** After the section has read what is stored: that is the starting point, not a change. */
    function markLoaded() {
        stored = current();
        status.value = "idle";
        message.value = null;
        cancelTimer();
    }

    function cancelTimer() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    }

    watch(() => current(), () => {
        if (stored === null) {
            return;
        }
        cancelTimer();
        if (!isChanged()) {
            // Back to what is stored: nothing waits, and a refusal of something else is moot.
            if (status.value == "waiting" || status.value == "invalid" || status.value == "error") {
                status.value = "idle";
                message.value = null;
            }
            return;
        }
        const reason = options.validate?.() ?? null;
        if (reason) {
            status.value = "invalid";
            message.value = reason;
            return;
        }
        if (status.value != "saving") {
            status.value = "waiting";
        }
        timer = setTimeout(() => void saveNow(), delay);
    });

    /** Sends what has not been stored, and answers whether it is stored now. */
    async function saveNow(): Promise<boolean> {
        cancelTimer();
        // The change after it goes when it is back - and a loop, as another caller waiting on
        // the same save may have sent the next one by the time this one wakes.
        while (inFlight) {
            await inFlight;
        }
        if (!isChanged()) {
            return status.value != "error";
        }
        const reason = options.validate?.() ?? null;
        if (reason) {
            status.value = "invalid";
            message.value = reason;
            return false;
        }

        const sending = current();
        status.value = "saving";
        message.value = null;
        inFlight = new Promise<boolean>(resolve => {
            const api = options.request();
            api.setErrorHandler(response => {
                status.value = "error";
                message.value = String(response?.error ?? response?.message ?? "Could not save");
                resolve(false);
                return false;
            });
            api.save(options.data?.() ?? null, saved => {
                stored = sending;
                status.value = "saved";
                options.onSaved?.(saved);
                resolve(true);
            });
        });
        const ok = await inFlight;
        inFlight = null;

        // Changed while it was on its way: that goes too.
        if (ok && isChanged()) {
            return saveNow();
        }
        return ok;
    }

    function retry() {
        void saveNow();
    }

    /** Sends what is pending; asks only when it cannot be stored. */
    async function beforeLeaving(): Promise<boolean> {
        if (!isChanged()) {
            return true;
        }
        if (await saveNow()) {
            return true;
        }
        return new Promise(resolve => bus.emit("confirm", {
            body: `Not saved: ${message.value ?? "the change could not be stored"}. Leave anyway?`,
            responseCallback: (confirmed: boolean) => resolve(confirmed),
        }));
    }

    // A section is swapped within the same route, so both guards are needed.
    onBeforeRouteLeave(() => beforeLeaving());
    onBeforeRouteUpdate((to, from) => to.path == from.path || beforeLeaving());

    function onBeforeUnload(event: BeforeUnloadEvent) {
        if (isChanged()) {
            event.preventDefault();
        }
    }
    onMounted(() => window.addEventListener("beforeunload", onBeforeUnload));
    onUnmounted(() => {
        window.removeEventListener("beforeunload", onBeforeUnload);
        cancelTimer();
    });

    const autoSave = computed<AutoSaveState>(() => ({
        status: status.value,
        message: message.value,
        retry,
    }));

    return { autoSave, markLoaded, saveNow, isChanged };
}
