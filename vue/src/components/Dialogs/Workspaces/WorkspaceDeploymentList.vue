<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Deployment, DeploymentSpecification, Workspace} from "@/core/services/Deploy/models";
import DeploymentList from "@/components/Modules/Setup/Deployments/List/DeploymentList.vue";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

export interface WorkspaceDeploymentListDialog_Input {
    workspace: Workspace
}

interface CreateItem {
    inUse: boolean;
    deploymentSpecification: DeploymentSpecification;
}

const props = defineProps<{ input: WorkspaceDeploymentListDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const isLoadingCreateItems = ref(false);
const showCreateMenu = ref(false);
const createItems = ref<CreateItem[]>([]);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();

    isLoadingCreateItems.value = true;
    Api.workspaces().get()
        .where('id', props.input.workspace.id!)
        .include('deployment')
        .find(workspaces => {
            const usedDeploymentSpecificationIds = workspaces[0].deployments
                ?.map(deployment => deployment.deployment_specification_id!)
                ?? []
            Api.deploymentSpecifications().get()
                .include('container_image')
                .find(items => {
                    createItems.value = items.map(item => {
                        return {
                            inUse: usedDeploymentSpecificationIds.includes(item.id!),
                            deploymentSpecification: item,
                        };
                    }).sort((a, b) => a.deploymentSpecification.name?.localeCompare(b.deploymentSpecification.name ?? '') ?? 0);
                    isLoadingCreateItems.value = false;
                });
        });
});

onUnmounted(() => {
});

function render() {
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    close();
}

function onCloseBtnClicked() {
    close();
}

function onCreateItemBtnClicked(createItem: CreateItem) {
    showCreateMenu.value = false;
    bus.emit('deploymentCreate', {
        spec: createItem.deploymentSpecification,
        workspace: props.input.workspace,
        onSavedCallback: (deployment: Deployment) => onItemCreatedEvent(deployment),
    });
}

function onItemCreatedEvent(deployment: Deployment) {
    const createItem = createItems.value.find(createItem => createItem.deploymentSpecification.id == deployment.deployment_specification_id);
    if (createItem) {
        createItem.inUse = true;
    }
}

function onItemDeletedEvent(deployment: Deployment) {
    const createItem = createItems.value.find(createItem => createItem.deploymentSpecification.id == deployment.deployment_specification_id);
    if (createItem) {
        createItem.inUse = false;
    }
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        height="60vh"
        width="80vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>
                <div class="w-100 d-flex">
                    <span class="my-auto">
                        {{ props.input.workspace.name }} - Deployments
                    </span>

                    <div
                        class="ml-auto"
                    >
                        <v-menu
                            v-model="showCreateMenu"
                            :close-on-content-click="false"
                            left
                            min-width="250"
                            offset-y>
                            <template v-slot:activator="{ props }">
                                <v-btn
                                    v-bind="props"
                                    small
                                    variant="text"
                                    prepend-icon="fa fa-plus"
                                    :loading="isLoadingCreateItems"
                                >
                                    Create
                                </v-btn>
                            </template>

                            <v-list
                                class="list-items">
                                <div
                                    v-for="(createItem, i) in createItems" :key="i"
                                >
                                    <v-list-item
                                        dense
                                        :disabled="createItem.inUse"
                                        @click="onCreateItemBtnClicked(createItem)"
                                    >
                                        <v-list-item-title>
                                            <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                                            <span class="ml-2">{{ createItem.deploymentSpecification.name }}</span>
                                        </v-list-item-title>
                                    </v-list-item>
                                    <v-tooltip
                                        v-if="createItem.inUse"
                                        activator="parent" location="bottom">
                                        Already in use
                                    </v-tooltip>
                                </div>
                            </v-list>
                        </v-menu>
                    </div>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <DeploymentList
                    :show-header="false"
                    :filter-by-workspace-id="props.input.workspace.id"
                    @on-item-deleted="onItemDeletedEvent"
                    @on-item-saved="onItemCreatedEvent"
                />
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Close
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
