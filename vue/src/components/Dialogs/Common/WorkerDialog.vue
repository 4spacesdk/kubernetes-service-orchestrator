<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {EventEmitter} from "@/helpers/EventEmitter";

export interface WorkerDialog_Input {
    title?: string;
    body: string;
    onFinishBody?: string;
    onIsWorkingChangeEventEmitter: EventEmitter<boolean>;
}

const props = defineProps<{input: WorkerDialog_Input, events: DialogEventsInterface}>();

const used = ref(false);
const showDialog = ref(false);
const titleText = ref('');
const bodyText = ref('');
const isWorking = ref(true);

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
    props.input.onIsWorkingChangeEventEmitter.subscribe(value => {
        isWorking.value = value ?? false;
    });
    titleText.value = props.input.title ?? '';
    bodyText.value = props.input.body;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

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
            <v-card-title >{{ titleText }}</v-card-title>
            <v-divider/>
            <v-card-text>
                <span v-if="isWorking" v-html="bodyText"></span>
                <span v-if="!isWorking" v-html="props.input.onFinishBody ?? 'All done'"></span>
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Dismiss
                    <v-tooltip activator="parent">Work will continue</v-tooltip>
                </v-btn>
                <v-spacer/>
                <v-btn
                    :loading="isWorking"
                    variant="tonal"
                    color="success"
                    prepend-icon="fa fa-check"
                    @click="onCloseBtnClicked">
                    Close
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
