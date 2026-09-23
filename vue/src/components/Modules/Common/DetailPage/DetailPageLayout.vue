<script setup lang="ts" generic="T">
import {computed, ref} from 'vue'
import {useRoute} from "vue-router";
import {useDisplay} from "vuetify";
import {
    groupSections,
    isSectionEnabled,
    type DetailSection
} from "@/components/Modules/Common/DetailPage/detailSections";

/**
 * A page for one thing: its name in the toolbar, its sections in a menu down the side, and the
 * chosen one beside it. The section is `:section` in the route, left out for the first. Moving
 * between sections replaces the history entry, so Back leaves the page rather than stepping
 * back through the sections.
 */
const props = defineProps<{
    /** Undefined while it loads. */
    item?: T;
    isNotFound?: boolean;
    sections: DetailSection<T>[];
    /** The route the page is on, with `:id` and `:section?`. */
    routeName: string;
    /** Given to the section's component. */
    sectionProps: Record<string, unknown>;
    groupTitle?: (group: string, item: T) => string;
    notFoundText?: string;
}>();

const emit = defineEmits<{
    (e: 'back'): void
}>();

const route = useRoute();

/** A phone: the side menu would take the whole width, so it is a menu under the toolbar instead. */
const {xs: isPhone} = useDisplay();
const showSectionMenu = ref(false);

const sectionKey = computed(() => (route.params.section as string) ?? '');
const section = computed(() => props.sections.find(section => section.key == sectionKey.value));
const groups = computed(() => props.item ? groupSections(props.sections, props.item) : []);

function sectionRoute(key: string) {
    return {
        name: props.routeName,
        params: {...route.params, section: key || undefined},
    };
}

function titleOf(group: string) {
    return props.groupTitle && props.item ? props.groupTitle(group, props.item) : group;
}

</script>

<template>
    <div class="h-100 content-wrapper d-flex flex-column">

        <v-toolbar
            density="compact"
            flat
            color="toolbar"
            dark
        >
            <v-btn
                icon
                size="small"
                @click="emit('back')">
                <v-icon>fa fa-arrow-left</v-icon>
                <v-tooltip activator="parent" location="bottom">Back</v-tooltip>
            </v-btn>
            <v-toolbar-title>
                <slot
                    v-if="props.item"
                    name="title"/>
            </v-toolbar-title>
            <slot
                v-if="props.item"
                name="actions"/>
        </v-toolbar>

        <div
            v-if="props.isNotFound"
            class="pa-4">
            {{ props.notFoundText ?? 'This does not exist.' }}
        </div>

        <div
            v-else-if="props.item"
            class="d-flex flex-grow-1 page-body"
            :class="{'flex-column': isPhone}">

            <v-menu
                v-if="isPhone"
                v-model="showSectionMenu"
                location="bottom start">
                <template v-slot:activator="{ props: menuProps }">
                    <button
                        v-bind="menuProps"
                        class="section-picker">
                        <v-icon size="x-small">{{ section?.icon }}</v-icon>
                        <span>{{ section?.title ?? 'Sections' }}</span>
                        <v-icon size="x-small" class="ml-auto">fa fa-chevron-down</v-icon>
                    </button>
                </template>
                <v-card class="section-picker-menu">
                    <v-list
                        density="compact"
                        nav>
                        <template
                            v-for="group in groups"
                            :key="group.group">
                            <v-list-subheader v-if="group.group">{{ titleOf(group.group) }}</v-list-subheader>
                            <v-list-item
                                v-for="entry in group.sections"
                                :key="entry.key"
                                :to="sectionRoute(entry.key)"
                                replace
                                :active="entry.key == sectionKey"
                                :disabled="!isSectionEnabled(entry, props.item)"
                                :prepend-icon="entry.icon"
                                :title="entry.title"
                                color="secondary"
                                exact/>
                        </template>
                    </v-list>
                </v-card>
            </v-menu>

            <nav
                v-else
                class="section-menu">
                <v-list
                    density="compact"
                    bg-color="transparent"
                    nav>
                    <template
                        v-for="group in groups"
                        :key="group.group">
                        <v-list-subheader v-if="group.group">{{ titleOf(group.group) }}</v-list-subheader>
                        <v-list-item
                            v-for="entry in group.sections"
                            :key="entry.key"
                            :to="sectionRoute(entry.key)"
                            replace
                            :active="entry.key == sectionKey"
                            :disabled="!isSectionEnabled(entry, props.item)"
                            color="secondary"
                            exact>
                            <template v-slot:prepend>
                                <v-icon size="x-small">{{ entry.icon }}</v-icon>
                            </template>
                            <v-list-item-title>{{ entry.title }}</v-list-item-title>
                            <v-list-item-subtitle v-if="!isSectionEnabled(entry, props.item)">{{ entry.disabledHint }}</v-list-item-subtitle>
                        </v-list-item>
                    </template>
                </v-list>
            </nav>

            <main class="section-content flex-grow-1">
                <div
                    v-if="!section || !section.isShown(props.item)"
                    class="pa-4">
                    There is no such section here.
                </div>
                <div
                    v-else-if="!isSectionEnabled(section, props.item)"
                    class="pa-4">
                    {{ section.disabledHint }}.
                </div>
                <component
                    v-else
                    :is="section.component"
                    :key="`${route.params.id}-${section.key}`"
                    v-bind="props.sectionProps"/>
            </main>
        </div>

        <v-progress-linear
            v-else
            indeterminate/>
    </div>
</template>

<style scoped>
.page-body {
    min-height: 0;
}

.section-menu {
    width: 230px;
    flex-shrink: 0;
    overflow-y: auto;
    padding: .5rem 0;
    background: rgb(var(--v-theme-surface));
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.section-menu .v-list-item:not(:last-of-type) {
    border-bottom: none;
}

.section-menu .v-list-subheader {
    min-height: 28px;
    margin-top: .5rem;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.section-menu .v-list-item {
    min-height: 30px !important;
    margin: 0 .5rem 1px;
}

.section-menu :deep(.v-list-item__prepend) {
    width: 24px;
}

.section-menu :deep(.v-list-item__prepend > .v-icon) {
    margin-inline-end: 0;
    opacity: .7;
}

.section-menu :deep(.v-list-item-title) {
    font-size: 13px !important;
}

.section-menu :deep(.v-list-item-subtitle) {
    white-space: normal;
    font-size: 11px;
}

.section-content {
    min-width: 0;
    min-height: 0;
    overflow-y: auto;
}

.section-picker {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 500;
    background: rgb(var(--v-theme-surface));
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.section-picker-menu {
    width: calc(100vw - 16px);
    max-height: 70vh;
    overflow-y: auto;
}

.section-picker-menu .v-list-item:not(:last-of-type) {
    border-bottom: none;
}
</style>
