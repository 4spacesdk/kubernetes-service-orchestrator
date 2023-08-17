<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, DeploymentSpecification, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentCreateDialog_Input {
    spec: DeploymentSpecification;
    workspace?: Workspace;

    onSavedCallback?: (deployment: Deployment) => void;
}

const props = defineProps<{ input: DeploymentCreateDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const item = ref<Deployment>(new Deployment());
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
    item.value.name = props.input.spec.name;
    item.value.namespace = props.input.workspace?.namespace ?? '';
    item.value.image = props.input.spec.container_image?.url;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    isSaving.value = true;

    const api = props.input.workspace !== undefined
        ? Api.workspaces().createDeploymentPostById(props.input.workspace.id!)
        : Api.deployments().createPost();

    api
        .deploymentSpecificationId(props.input!.spec!.id!)
        .namespace(item.value.namespace!)
        .name(item.value.name!);

    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save(item.value!, newItem => {
        if (newItem) {
            isSaving.value = false;
            bus.emit('deploymentSaved', newItem);
            if (props.input.onSavedCallback) {
                props.input.onSavedCallback(newItem);
            }
            close();
        }
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
        height="60vh"
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>
                <span v-if="props.input.workspace">{{ props.input.workspace.name }} - </span>
                <span>{{ props.input.spec.name }}</span>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-text-field
                            v-model="item.image"
                            :disabled="true"
                            variant="outlined"
                            label="Image"/>
                    </v-col>
                    <v-col cols="4">
                        <v-checkbox
                            v-model="props.input.spec.enable_database"
                            :disabled="true"
                            color="black"
                            label="Database"/>
                    </v-col>
                    <v-col cols="4">
                        <v-checkbox
                            v-model="props.input.spec.enable_cronjob"
                            :disabled="true"
                            color="black"
                            label="CronJob"/>
                    </v-col>
                    <v-col cols="4">
                        <v-checkbox
                            v-model="props.input.spec.enable_ingress"
                            :disabled="true"
                            color="black"
                            label="Ingress"/>
                    </v-col>

                    <v-col cols="6">
                        <v-text-field
                            v-model="item.namespace"
                            :disabled="props.input.workspace !== undefined"
                            variant="outlined"
                            :rules="[
                                v => /^[a-z0-9]{1,10}$/.test(v) || 'Invalid format'
                            ]"
                            label="Namespace"
                            clearable
                            persistent-hint
                            hint="Max 10 characters, lowercase-only"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model="item.name"
                            variant="outlined"
                            :rules="[
                                v => /^[a-z0-9]{1,61}$/.test(v) || 'Invalid format'
                            ]"
                            label="Name"
                            clearable
                            persistent-hint
                            hint="Max 63 characters, lowercase-only"/>
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
