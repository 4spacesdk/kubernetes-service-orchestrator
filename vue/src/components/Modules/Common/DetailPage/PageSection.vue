<script setup lang="ts">
import type { AutoSaveState } from "@/composables/useAutoSave";

/**
 * One section of a detail page: a header with its title, the section's own buttons and Save,
 * and its content below, flat on the page as a list page is. Ctrl/Cmd+Enter saves from
 * anywhere in it, as in a dialog.
 *
 * A section that saves as it goes (`useAutoSave`) passes its state instead, and the header says
 * where the save is in place of the button: saving, saved, or not saved and why, with Retry.
 */
const props = defineProps<{
    title: string;
    isLoading?: boolean;
    isSaving?: boolean;
    /** Leaves Save out, for a section that saves as it goes or not at all. */
    hideSave?: boolean;
    /** The section saves as it goes: its state in the header, and no Save. */
    autoSave?: AutoSaveState;
    /** What Save says, when it does more than save - a version rolls out. */
    saveLabel?: string;
    /** No space around the content, for a list that runs edge to edge as on a list page. */
    flush?: boolean;
    /**
     * Exactly the height of the page, for content that scrolls itself - a log follows its newest
     * line only if it is the log that scrolls, not the page around it.
     */
    fill?: boolean;
}>();

const emit = defineEmits<{
    (e: 'save'): void
}>();

function onKeyDown(event: KeyboardEvent) {
    if (event.key == 'Enter' && (event.ctrlKey || event.metaKey)) {
        if (props.autoSave) {
            event.preventDefault();
            props.autoSave.retry();
        } else if (!props.hideSave) {
            event.preventDefault();
            emit('save');
        }
    }
}
</script>

<template>
    <section
        class="page-section"
        :class="{'is-fill': props.fill}"
        @keydown="onKeyDown">
        <header class="page-section-header">
            <h2 class="page-section-title">{{ props.title }}</h2>
            <div class="d-flex align-center ga-1 ml-auto">
                <slot name="actions"/>
                <div
                    v-if="props.autoSave"
                    class="auto-save ml-2"
                    :class="`is-${props.autoSave.status}`"
                    role="status"
                    aria-live="polite">
                    <template v-if="props.autoSave.status == 'saving' || props.autoSave.status == 'waiting'">
                        <v-progress-circular indeterminate size="12" width="2" class="mr-2"/>
                        Saving…
                    </template>
                    <template v-else-if="props.autoSave.status == 'saved'">
                        <v-icon size="x-small" color="success" class="mr-2">fa fa-check</v-icon>
                        Saved
                    </template>
                    <template v-else-if="props.autoSave.status == 'invalid'">
                        <v-icon size="x-small" color="warning" class="mr-2">fa fa-circle-exclamation</v-icon>
                        <span class="auto-save-message">Not saved: {{ props.autoSave.message }}</span>
                    </template>
                    <template v-else-if="props.autoSave.status == 'error'">
                        <v-icon size="x-small" color="error" class="mr-2">fa fa-triangle-exclamation</v-icon>
                        <span class="auto-save-message">Not saved: {{ props.autoSave.message }}</span>
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="error"
                            class="ml-2"
                            @click="props.autoSave.retry()">
                            Retry
                        </v-btn>
                    </template>
                    <template v-else>
                        <span class="text-medium-emphasis">Saves as you go</span>
                    </template>
                </div>
                <v-btn
                    v-else-if="!props.hideSave"
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="success"
                    class="ml-2"
                    :loading="props.isSaving"
                    :disabled="props.isLoading"
                    @click="emit('save')">
                    {{ props.saveLabel ?? 'Save' }}
                </v-btn>
            </div>
        </header>
        <v-progress-linear
            :active="props.isLoading"
            indeterminate
            height="2"/>
        <div
            class="page-section-body"
            :class="{'is-loading': props.isLoading, 'is-flush': props.flush}">
            <slot/>
        </div>
    </section>
</template>

<style scoped>
.page-section-header {
    position: sticky;
    top: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    min-height: 48px;
    padding: 0 1rem;
    background: rgb(var(--v-theme-background));
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.auto-save {
    display: flex;
    align-items: center;
    font-size: 13px;
    min-width: 0;
}

.auto-save-message {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: min(28rem, 45vw);
}

.page-section-title {
    font-size: 15px;
    font-weight: 500;
}

.page-section-body {
    padding: 1rem;
}

.page-section-body.is-flush {
    padding: 0;
}

.page-section.is-fill {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.page-section.is-fill .page-section-body {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.page-section-body.is-loading {
    pointer-events: none;
    opacity: 0.6;
}
</style>
