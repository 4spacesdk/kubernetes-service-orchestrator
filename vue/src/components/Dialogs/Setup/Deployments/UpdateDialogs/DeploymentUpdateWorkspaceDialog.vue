<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentUpdateWorkspaceDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateWorkspaceDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const value = ref<number>();
const workspaceItems = ref<Workspace[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

onUnmounted(() => {
});

function render() {
    value.value = props.input.deployment.workspace_id;
    showDialog.value = true;

    isLoading.value = true;
    Api.workspaces().get().find(workspaces => {
        workspaceItems.value = workspaces;
        isLoading.value = false;
    });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.deployments().updateWorkspacePutById(props.input.deployment.id!)
        .value(value.value!)
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save(null, newItem => {
        props.input.deployment.workspace_id = newItem.workspace_id;
        props.input.deployment.workspace = newItem.workspace;
        bus.emit('deploymentSaved', newItem);
        close();
        isSaving.value = false;
    });
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        max-width="300px"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>Deployment</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-select
                            v-model="value"
                            :loading="isLoading"
                            :items="workspaceItems"
                            item-value="id"
                            item-title="name"
                            variant="outlined"
                            label="Workspace"
                        >
                            <template v-slot:item="{ props, item }">
                                <v-list-item
                                    v-bind="props"
                                    :subtitle="`Namespace: ${item.raw.namespace}`"
                                />
                            </template>
                        </v-select>
                    </v-col>
                </v-row>
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

                <v-btn
                    :loading="isSaving"
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="green"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
