import { ref } from "vue";
import bus from "@/plugins/bus";

interface SavingApi<T> {
    setErrorHandler(handler: (response: any) => boolean): unknown;
    save(data: any, next?: (value: T) => void): unknown;
}

/**
 * Saving from a dialog: validate the form, send once, and close only when the
 * server said yes. A refusal is shown and the dialog stays open with what was typed.
 *
 * Many dialogs used to call `close()` straight after `save()`, so a failed save vanished
 * without a word and a double click sent two requests.
 *
 *     const { form, isSaving, save } = useDialogSave();
 *     save(api, item.value, saved => { bus.emit("xSaved", saved); close(); });
 *
 * Bind `ref="form"` on a `<v-form>` to have its rules checked first, and `:loading="isSaving"`
 * on the Save button.
 */
export function useDialogSave() {
    const isSaving = ref(false);
    const error = ref<string | null>(null);
    const form = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);

    async function save<T>(api: SavingApi<T>, data: any, onSaved: (saved: T) => void) {
        if (isSaving.value) {
            return;
        }
        if (form.value) {
            const { valid } = await form.value.validate();
            if (!valid) {
                return;
            }
        }

        isSaving.value = true;
        error.value = null;
        api.setErrorHandler(response => {
            isSaving.value = false;
            error.value = String(response?.error ?? response?.message ?? "Could not save");
            bus.emit("toast", { text: error.value, color: "error" });
            return false;
        });
        api.save(data, saved => {
            isSaving.value = false;
            onSaved(saved);
        });
    }

    return { form, isSaving, error, save };
}
