<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import CodeEditor from 'simple-code-editor';
import VariableBtn from "@/components/Modules/Common/VariableBtn.vue";

export interface DeploymentUpdateCustomResourceDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateCustomResourceDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const content = ref<string>();

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
    content.value = props.input.deployment.custom_resource ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = Api.deployments().updateCustomResourcePutById(props.input.deployment.id!)
        .content(content.value!);
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

function onVariableClicked(text: string) {
    navigator.clipboard.writeText(text);
    bus.emit('toast', {
        text: `Variables copied to clipboard`
    });
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
                        <div
                            style="position: relative"
                        >
                            <CodeEditor
                                v-model="content"
                                :languages="[['yaml']]"
                                class="w-100"
                                height="500px"
                                theme="atom-one-dark"
                                font-size="13px"
                                :line-nums="true"
                            />

                            <div
                                style="position: absolute; top: -2px; right: 40px;"
                            >
                                <variable-btn
                                    color="grey"
                                    @add-variable="newText => onVariableClicked(newText)"
                                />
                            </div>
                        </div>
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

.code-editor {
    letter-spacing: 0 !important;
    line-height: 0 !important;
}
</style>
