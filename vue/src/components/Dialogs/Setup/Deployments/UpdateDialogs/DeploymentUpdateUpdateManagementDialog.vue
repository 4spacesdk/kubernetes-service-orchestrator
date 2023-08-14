<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentUpdateUpdateManagementDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateUpdateManagementDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const keelPolicy = ref<string>();
const keelPolicies = ref<string[]>([]);
const isLoadingKeelPolicies = ref(false);
const keelAutoUpdate = ref<boolean>();
const enablePodioNotifications = ref<boolean>();

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
    keelPolicy.value = props.input.deployment.keel_policy ?? '';
    keelAutoUpdate.value = props.input.deployment.keel_auto_update ?? false;
    enablePodioNotifications.value = props.input.deployment.enable_podio_notification ?? false;
    showDialog.value = true;

    isLoadingKeelPolicies.value = true;
    Api.keelPolicies().getGet()
        .find(response => {
            keelPolicies.value = response.map(item => item.name!);
            isLoadingKeelPolicies.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = Api.deployments().updateUpdateManagementPutById(props.input.deployment.id!)
        .keelPolicy(keelPolicy.value!)
        .keelAutoUpdate(keelAutoUpdate.value!)
        .enablePodioNotification(enablePodioNotifications.value!);
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
                        <v-select
                            v-model="keelPolicy"
                            :loading="isLoadingKeelPolicies"
                            :items="keelPolicies"
                            variant="outlined"
                            label="Keel Policies"/>
                    </v-col>
                    <v-col cols="6">
                        <v-checkbox
                            v-model="keelAutoUpdate"
                            density="compact"
                            label="Auto Update"/>
                    </v-col>
                    <v-col cols="6">
                        <v-checkbox
                            v-model="enablePodioNotifications"
                            density="compact"
                            label="Podio Notification"/>
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
