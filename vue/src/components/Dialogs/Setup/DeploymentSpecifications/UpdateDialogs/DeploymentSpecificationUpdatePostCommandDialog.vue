<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationUpdatePostCommandDialog_Input {
    postCommand: {
        name: string;
        command: string;
        allPods: boolean;
        container: string;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{ input: DeploymentSpecificationUpdatePostCommandDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const name = ref('');
const command = ref('');
const allPods = ref(false);
const container = ref('');

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
    name.value = props.input.postCommand.name ?? '';
    command.value = props.input.postCommand.command ?? '';
    allPods.value = props.input.postCommand.allPods ?? false;
    container.value = props.input.postCommand.container ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.postCommand.name = name.value;
    props.input.postCommand.command = command.value;
    props.input.postCommand.allPods = allPods.value;
    props.input.postCommand.container = container.value;
    props.input.onSaveCallback();
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
            <v-card-title>Post Command</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row>
                    <v-col cols="12">
                        <v-text-field
                            v-model="name"
                            variant="outlined"
                            label="Name"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            v-model="command"
                            variant="outlined"
                            label="Command"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            v-model="container"
                            variant="outlined"
                            label="Container"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-checkbox
                            v-model="allPods"
                            variant="outlined"
                            label="Run on all pods"
                            hide-details
                        />
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
                    Done
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
