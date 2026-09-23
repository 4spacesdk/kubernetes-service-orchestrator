<script setup lang="ts">
/**
 * One section of a detail page: a header with its title, the section's own buttons and Save,
 * and its content below, flat on the page as a list page is. Ctrl/Cmd+Enter saves from
 * anywhere in it, as in a dialog.
 */
const props = defineProps<{
    title: string;
    isLoading?: boolean;
    isSaving?: boolean;
    /** Leaves Save out, for a section that saves as it goes or not at all. */
    hideSave?: boolean;
}>();

const emit = defineEmits<{
    (e: 'save'): void
}>();

function onKeyDown(event: KeyboardEvent) {
    if (event.key == 'Enter' && (event.ctrlKey || event.metaKey) && !props.hideSave) {
        event.preventDefault();
        emit('save');
    }
}
</script>

<template>
    <section
        class="page-section"
        @keydown="onKeyDown">
        <header class="page-section-header">
            <h2 class="page-section-title">{{ props.title }}</h2>
            <div class="d-flex align-center ga-1 ml-auto">
                <slot name="actions"/>
                <v-btn
                    v-if="!props.hideSave"
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="success"
                    class="ml-2"
                    :loading="props.isSaving"
                    :disabled="props.isLoading"
                    @click="emit('save')">
                    Save
                </v-btn>
            </div>
        </header>
        <v-progress-linear
            :active="props.isLoading"
            indeterminate
            height="2"/>
        <div
            class="page-section-body"
            :class="{'is-loading': props.isLoading}">
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

.page-section-title {
    font-size: 15px;
    font-weight: 500;
}

.page-section-body {
    padding: 1rem;
}

.page-section-body.is-loading {
    pointer-events: none;
    opacity: 0.6;
}
</style>
