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

const enabled = ref<boolean>();
const tagRegex = ref<string>();
const requireApproval = ref<boolean>();

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
    enabled.value = props.input.deployment.auto_update_enabled ?? false;
    tagRegex.value = props.input.deployment.auto_update_tag_regex ?? '';
    requireApproval.value = props.input.deployment.auto_update_require_approval ?? false;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = Api.deployments().updateUpdateManagementPutById(props.input.deployment.id!)
        .enabled(enabled.value!)
        .tagRegex(tagRegex.value!)
        .requireApproval(requireApproval.value!);
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
                        <v-checkbox
                            v-model="enabled"
                            density="compact"
                            label="Enabled"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model="tagRegex"
                            variant="outlined"
                            label="Tag regex"/>
                    </v-col>
                    <v-col cols="6">
                        <v-checkbox
                            v-model="requireApproval"
                            density="compact"
                            label="Require approval"/>
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
