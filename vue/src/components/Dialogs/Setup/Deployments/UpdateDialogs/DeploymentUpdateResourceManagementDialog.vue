<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentUpdateResourceManagementDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateResourceManagementDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const cpuLimit = ref<number>();
const cpuRequest = ref<number>();
const memoryLimit = ref<number>();
const memoryRequest = ref<number>();
const replicas = ref<number>();

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
    cpuLimit.value = props.input.deployment.cpu_limit ?? 0;
    cpuRequest.value = props.input.deployment.cpu_request ?? 0;
    memoryLimit.value = props.input.deployment.memory_limit ?? 0;
    memoryRequest.value = props.input.deployment.memory_request ?? 0;
    replicas.value = props.input.deployment.replicas ?? 0;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = Api.deployments().updateResourceManagemnetPutById(props.input.deployment.id!)
        .cpuLimit(cpuLimit.value!)
        .cpuRequest(cpuRequest.value!)
        .memoryLimit(memoryLimit.value!)
        .memoryRequest(memoryRequest.value!)
        .replicas(replicas.value!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        return false;
    });
    api.save(null, newItem => {
        bus.emit('deploymentSaved', newItem);
        close();
    });

    close();
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>Deployment</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="6">
                        <v-text-field
                            v-model.number="cpuRequest"
                            type="number"
                            variant="outlined"
                            hint="100 = 0.1 CPU"
                            persistent-hint
                            label="CPU Request"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model.number="cpuLimit"
                            type="number"
                            variant="outlined"
                            hint="500 = 0.5 CPU"
                            persistent-hint
                            label="CPU Limit"/>
                    </v-col>

                    <v-col cols="6">
                        <v-text-field
                            v-model.number="memoryRequest"
                            type="number"
                            variant="outlined"
                            hint="190.73 = 200 MB"
                            persistent-hint
                            label="Memory Request"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model.number="memoryLimit"
                            type="number"
                            variant="outlined"
                            hint="953.67 = 1 GB"
                            persistent-hint
                            label="Memory Limit"/>
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            v-model.number="replicas"
                            type="number"
                            variant="outlined"
                            hint="0 = not running, 1 = testing purposes, 3 = production"
                            persistent-hint
                            label="Replicas"/>
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
