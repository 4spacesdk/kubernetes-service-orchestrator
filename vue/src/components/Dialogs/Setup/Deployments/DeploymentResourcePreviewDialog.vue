<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Deployment} from "@/core/services/Deploy/models";
import type {DeploymentStep} from "@/core/services/Deploy/Api";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import JsonViewer from 'vue-json-viewer';

export interface DeploymentResourcePreviewDialog_Input {
    deployment: Deployment,
    step: DeploymentStep,
}

const props = defineProps<{ input: DeploymentResourcePreviewDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);
const tab = ref<'diff' | 'local' | 'remote'>('diff');
const localJSON = ref('');
const remoteJSON = ref('');
const localString = ref('');
const remoteString = ref('');

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
    showDialog.value = true;
    reload();
}

function reload() {
    isLoading.value = true;
    const api = Api.deploymentSteps().getPreviewGetByIdentifier(props.input.step.identifier!)
        .deploymentId(props.input.deployment.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isLoading.value = false;
        return false;
    });
    api.find(response => {
        if (response && response.length == 1) {
            const preview = JSON.parse(response[0].value!);
            if (preview) {
                localJSON.value = JSON.parse(preview.local ?? '{}');
                remoteJSON.value = JSON.parse(preview.remote ?? '{}');

                localString.value = JSON.stringify(localJSON.value, null, 2);
                remoteString.value = JSON.stringify(remoteJSON.value, null, 2);
            }
        }
        isLoading.value = false;
    });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onReloadBtnClicked() {
    reload();
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        height="80vh"
        width="80vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading">
            <v-card-title>
                <div class="d-flex">
                    <v-chip class="my-auto">{{ props.input.deployment.name }}.{{
                            props.input.deployment.namespace
                        }}
                    </v-chip>
                    <span class="my-auto ml-2">{{ props.input.step.name }} - Preview</span>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>

                <v-row>
                    <v-col cols="12">
                        <v-tabs
                            v-model="tab"
                            bg-color="primary"
                        >
                            <v-tab value="diff">Diff</v-tab>
                            <v-tab value="local">Local</v-tab>
                            <v-tab value="remote">Remote</v-tab>
                        </v-tabs>
                    </v-col>
                    <v-col cols="12">
                        <v-window v-model="tab">
                            <v-window-item value="diff">
                                <Diff
                                    mode="split"
                                    theme="dark"
                                    :prev="remoteString"
                                    :current="localString"
                                    language="json"
                                    :folding="true"
                                    :input-delay="0"
                                    :virtual-scroll="false"
                                />
                            </v-window-item>

                            <v-window-item value="local">
                                <json-viewer
                                    :expand-depth=10
                                    copyable
                                    :value="localJSON"/>
                            </v-window-item>

                            <v-window-item value="remote">
                                <json-viewer
                                    :expand-depth=10
                                    copyable
                                    :value="remoteJSON"/>
                            </v-window-item>
                        </v-window>
                    </v-col>
                </v-row>

            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-refresh"
                    @click="onReloadBtnClicked">
                    Reload
                </v-btn>
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
