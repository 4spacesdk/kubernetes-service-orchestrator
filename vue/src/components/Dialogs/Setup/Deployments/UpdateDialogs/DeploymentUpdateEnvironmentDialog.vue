<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentUpdateEnvironmentDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateEnvironmentDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const value = ref<string>();
const items = ref<string[]>([]);
const isLoading = ref(false);

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
    value.value = props.input.deployment.environment ?? '';
    showDialog.value = true;

    isLoading.value = true;
    Api.environments().getGet()
        .find(response => {
            items.value = response.map(item => item.name!);
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
    const api = Api.deployments().updateEnvironmentPutById(props.input.deployment.id!)
        .value(value.value!)
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
                    <v-col cols="12">
                        <v-select
                            v-model="value"
                            :loading="isLoading"
                            :items="items"
                            variant="outlined"
                            label="Environments"/>
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
