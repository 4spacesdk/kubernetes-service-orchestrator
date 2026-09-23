<script setup lang="ts">
import {computed} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {isSectionEnabled} from "@/components/Modules/Common/DetailPage/detailSections";
import {workspaceSections} from "@/components/Modules/Workspaces/Sections/sections";

/** The workspace's settings, each a section of its page - the same list its side menu reads. */
const props = defineProps<{
    workspace: Workspace
}>();

const settings = computed(() => workspaceSections
    .filter(section => section.group == 'Settings' && section.isShown(props.workspace)));
</script>

<template>
    <div
        class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">
            <v-list
                class="list-items">
                <div
                    v-for="section in settings"
                    :key="section.key">
                    <v-list-item
                        :disabled="!isSectionEnabled(section, props.workspace)"
                        :to="{name: 'WorkspaceById', params: {id: props.workspace.id, section: section.key}}"
                        dense>
                        <v-list-item-title>
                            <v-icon size="small" class="my-auto ml-2">{{ section.icon }}</v-icon>
                            <span class="ml-2">{{ section.title }}</span>
                        </v-list-item-title>
                    </v-list-item>
                    <v-tooltip
                        activator="parent"
                        :disabled="isSectionEnabled(section, props.workspace)"
                        location="left">{{ section.disabledHint }}
                    </v-tooltip>
                </div>
            </v-list>
        </v-card>
    </div>
</template>

<style scoped>
.list-wrapper {
    min-width: 120px;
}

.v-list-item {
    min-height: unset;
}

.v-list-item-title {
    font-size: 11px !important;
}
</style>
