<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {withoutScheme, workspaceUrls, type WorkspaceUrl} from "./workspaceUrls";

const props = defineProps<{
    workspace: Workspace,
}>();

type Row = WorkspaceUrl;

const text = ref('');
const rows = ref<Row[]>([]);

onMounted(() => {
    setup();
});

watch(() => props.workspace, newWorkspace => {
    setup();
});

onUnmounted(() => {

});

function setup() {
    rows.value = workspaceUrls(props.workspace);

    // Without the scheme: it costs 60px in a column that is already the widest. The whole
    // url is in the menu, and in the link that opens.
    text.value = rows.value.length > 0 ? withoutScheme(rows.value[0].url) : '';

    render();
}

function reload() {

}

function render() {

}

// <editor-fold desc="View function bindings">

function onDeploymentRowClicked(item: Row) {
    window.open(item.url, '_blank');
}

// </editor-fold>

</script>

<template>
    <v-menu
        left
        min-width="250"
        offset-y
        :open-on-hover="true"
    >
        <template v-slot:activator="{props}">
            <div
                class="d-flex justify-start align-center url"
                v-bind="props"
            >
                <span class="text-truncate">{{ text }}</span>
                <span v-if="rows.length > 1" class="more">+{{ rows.length - 1 }}</span>
            </div>
        </template>

        <v-card
            class="w-100 list-wrapper"
        >
            <v-list
                class="list-items"
            >
                <v-list-item
                    v-for="(row, i) in rows" :key="i"
                    @click="onDeploymentRowClicked(row)"
                >
                    <v-list-item-title>
                        {{ row.deployment.name }}
                    </v-list-item-title>
                    <v-list-item-subtitle>
                        {{ row.url }}
                    </v-list-item-subtitle>
                </v-list-item>
            </v-list>
        </v-card>
    </v-menu>
</template>

<style scoped>

.url {
    max-width: 220px;
}

.more {
    font-size: 11px;
    opacity: 0.6;
    margin-left: 4px;
}

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
