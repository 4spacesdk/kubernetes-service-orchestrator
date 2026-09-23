<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import DateView from "@/components/Modules/Common/DateView.vue";

/**
 * A deployment's version, changed where it is read: a click lists the image's tags, last
 * pushed first, and choosing one rolls it out - as the Version section does.
 */
const props = defineProps<{
    deployment: Deployment,
}>();

const showMenu = ref(false);
const search = ref('');
const tags = ref<string[]>([]);
/** When each tag was pushed, by name. A tag the registry gives no time for is missing. */
const pushedAt = ref<Record<string, string>>({});
const isLoadingTags = ref(false);
const isSaving = ref(false);

const filteredTags = computed(() => {
    const value = search.value.trim().toLowerCase();
    return value ? tags.value.filter(tag => tag.toLowerCase().includes(value)) : tags.value;
});

// Fetched when opened - a list of deployments would otherwise ask the registry once per row.
watch(showMenu, open => {
    if (open) {
        search.value = '';
        loadTags();
    }
});

function loadTags() {
    isLoadingTags.value = true;
    Api.deploymentSpecifications().getTagsGetById(props.deployment.deployment_specification_id!)
        .find(response => {
            pushedAt.value = Object.fromEntries(
                (response[0]?.tag_details ?? [])
                    .filter(tag => tag.pushed_at)
                    .map(tag => [tag.name!, tag.pushed_at!])
            );
            tags.value = [...(response[0]?.tags ?? [])]
                .reverse()
                .sort((a, b) => (pushedAt.value[b] ?? "").localeCompare(pushedAt.value[a] ?? ""));
            isLoadingTags.value = false;
        });
}

function onTagClicked(tag: string) {
    showMenu.value = false;
    if (tag == props.deployment.version || isSaving.value) {
        return;
    }
    isSaving.value = true;
    const api = Api.deployments().updateVersionPutById(props.deployment.id!)
        .value(tag);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {text: response.error});
        }
        isSaving.value = false;
        return false;
    });
    api.save(null, newItem => {
        bus.emit('deploymentSaved', newItem);
        bus.emit('toast', {text: `${props.deployment.name} is on ${tag}`});
        isSaving.value = false;
    });
}
</script>

<template>
    <v-menu
        v-model="showMenu"
        :close-on-content-click="false"
        location="bottom start">
        <template v-slot:activator="{ props: menuProps }">
            <button
                v-bind="menuProps"
                type="button"
                class="version"
                :disabled="isSaving"
                :aria-label="`Version: ${deployment.version || 'none'}. Change`">
                <span class="version-text">{{ deployment.version || '—' }}</span>
                <v-progress-circular v-if="isSaving" indeterminate size="12" width="2"/>
                <v-icon v-else size="10" class="version-chevron">fa fa-chevron-down</v-icon>
            </button>
        </template>

        <v-card class="version-menu">
            <v-text-field
                v-model="search"
                placeholder="Find a tag"
                prepend-inner-icon="fa fa-magnifying-glass"
                variant="solo-filled"
                flat
                density="compact"
                hide-details
                autofocus
                @keydown.enter="filteredTags.length && onTagClicked(filteredTags[0])"/>
            <v-progress-linear v-if="isLoadingTags" indeterminate color="secondary"/>
            <v-list density="compact" class="version-list">
                <v-list-item
                    v-for="tag in filteredTags"
                    :key="tag"
                    :active="tag == deployment.version"
                    color="secondary"
                    @click="onTagClicked(tag)">
                    <v-list-item-title>{{ tag }}</v-list-item-title>
                    <template v-if="pushedAt[tag]" v-slot:append>
                        <DateView :date-string="pushedAt[tag]" text-format="DD/MM-YY HH:mm" class="text-body-medium text-medium-emphasis ml-4 pushed-at"/>
                    </template>
                </v-list-item>
                <v-list-item
                    v-if="!isLoadingTags && !filteredTags.length"
                    disabled
                    title="No tags"/>
            </v-list>
        </v-card>
    </v-menu>
</template>

<style scoped>
.version {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 2px 6px;
    margin-left: -6px;
    border-radius: 6px;
    color: inherit;
}

.version:hover:not(:disabled),
.version:focus-visible {
    background: rgba(var(--v-theme-secondary), 0.12);
    color: rgb(var(--v-theme-secondary));
}

.version-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.version-chevron {
    opacity: 0;
}

.version:hover .version-chevron,
.version:focus-visible .version-chevron {
    opacity: 1;
}

.version-menu {
    width: 320px;
}

.version-list {
    max-height: 360px;
    overflow-y: auto;
}

/* Same width for every date, so they line up: Roboto kerns around a 1. */
.pushed-at {
    font-variant-numeric: tabular-nums;
    font-kerning: none;
}
</style>
