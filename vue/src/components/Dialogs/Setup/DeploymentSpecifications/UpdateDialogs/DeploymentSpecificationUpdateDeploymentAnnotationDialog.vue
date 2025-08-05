<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {VTextField} from "vuetify/components/VTextField";
import {DeploymentAnnotationLevels} from "@/constants";

export interface DeploymentSpecificationUpdateDeploymentAnnotationDialog_Input {
    deploymentAnnotation: {
        level: string;
        name: string;
        value: string;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateDeploymentAnnotationDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const level = ref('');
const name = ref('');
const value = ref('');
const levelItems = ref([
    DeploymentAnnotationLevels.Deployment,
    DeploymentAnnotationLevels.Pod,
]);

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
    level.value = props.input.deploymentAnnotation.level ?? '';
    name.value = props.input.deploymentAnnotation.name ?? '';
    value.value = props.input.deploymentAnnotation.value ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.deploymentAnnotation.level = level.value;
    props.input.deploymentAnnotation.name = name.value;
    props.input.deploymentAnnotation.value = value.value;
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
            <v-card-title>Deployment Annotation</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row>
                    <v-col cols="12">
                        <v-select
                            v-model="level"
                            variant="outlined"
                            label="Level"
                            :items="levelItems"
                            spellcheck="false"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            v-model="name"
                            variant="outlined"
                            label="Name"
                            spellcheck="false"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            v-model="value"
                            variant="outlined"
                            label="Value"
                            spellcheck="false"
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
