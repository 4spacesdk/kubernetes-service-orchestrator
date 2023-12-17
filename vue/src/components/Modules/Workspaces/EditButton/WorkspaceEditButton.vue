<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import type {DeploymentSpecGetResponse} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DeploymentStatusTypes} from "@/constants";

const props = defineProps<{
    workspace: Workspace
}>();

const spec = ref<DeploymentSpecGetResponse>();

const showUpdateName = ref(false);
const isUpdateNameEnabled = ref(false);

const showUpdateEmailService = ref(false);
const isUpdateEmailServiceEnabled = ref(false);

const showUpdateDatabaseService = ref(false);
const isUpdateDatabaseServiceEnabled = ref(false);

const showUpdateIngress = ref(false);
const isUpdateIngressEnabled = ref(false);

const showUpdateLabels = ref(false);
const isUpdateLabelsEnabled = ref(false);

onMounted(() => {
    render();
});

function render() {
    showUpdateName.value = true;
    isUpdateNameEnabled.value = true;

    showUpdateEmailService.value = true;
    isUpdateEmailServiceEnabled.value = true;

    showUpdateDatabaseService.value = true;
    isUpdateDatabaseServiceEnabled.value = props.workspace.deployments?.find(deployment => deployment.status !== DeploymentStatusTypes.Draft) === null;

    showUpdateIngress.value = true;
    isUpdateIngressEnabled.value = true;

    showUpdateLabels.value = true;
    isUpdateLabelsEnabled.value = true;
}

function onUpdateNameClicked() {
    bus.emit('workspaceUpdateName', {
        workspace: props.workspace
    });
}

function onUpdateEmailServiceClicked() {
    bus.emit('workspaceUpdateEmailService', {
        workspace: props.workspace
    });
}

function onUpdateDatabaseServiceClicked() {
    bus.emit('workspaceUpdateDatabaseService', {
        workspace: props.workspace
    });
}

function onUpdateIngressClicked() {
    bus.emit('workspaceUpdateIngress', {
        workspace: props.workspace
    });
}

function onUpdateLabelsClicked() {
    bus.emit('workspaceUpdateLabels', {
        workspace: props.workspace
    });
}

</script>

<template>
    <div
        class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">
            <v-list
                class="list-items">
                <v-list-item
                    v-if="showUpdateName"
                    :disabled="!isUpdateNameEnabled"
                    dense
                    @click="onUpdateNameClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-font</v-icon>
                        <span class="ml-2">Name</span>
                    </v-list-item-title>
                </v-list-item>

                <div
                    v-if="showUpdateEmailService">
                    <v-list-item
                        :disabled="!isUpdateEmailServiceEnabled"
                        dense
                        @click="onUpdateEmailServiceClicked">
                        <v-list-item-title>
                            <v-icon size="small" class="my-auto ml-2">fa fa-envelope</v-icon>
                            <span class="ml-2">Email Service</span>
                        </v-list-item-title>
                    </v-list-item>
                    <v-tooltip
                        activator="parent"
                        :disabled="isUpdateEmailServiceEnabled"
                        location="left">Only available during setup
                    </v-tooltip>
                </div>

                <div
                    v-if="showUpdateDatabaseService">
                    <v-list-item
                        :disabled="!isUpdateDatabaseServiceEnabled"
                        dense
                        @click="onUpdateDatabaseServiceClicked">
                        <v-list-item-title>
                            <v-icon size="small" class="my-auto ml-2">fa fa-database</v-icon>
                            <span class="ml-2">Database Service</span>
                        </v-list-item-title>
                    </v-list-item>
                    <v-tooltip
                        activator="parent"
                        :disabled="isUpdateDatabaseServiceEnabled"
                        location="left">Only available during setup
                    </v-tooltip>
                </div>

                <v-list-item
                    v-if="showUpdateIngress"
                    :disabled="!isUpdateIngressEnabled"
                    dense
                    @click="onUpdateIngressClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-code-merge</v-icon>
                        <span class="ml-2">Ingress</span>
                    </v-list-item-title>
                </v-list-item>

                <v-list-item
                    v-if="showUpdateLabels"
                    :disabled="!isUpdateLabelsEnabled"
                    dense
                    @click="onUpdateLabelsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-tags</v-icon>
                        <span class="ml-2">Labels</span>
                    </v-list-item-title>
                </v-list-item>
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

.v-progress-circular {
    margin: 1rem;
}
</style>
