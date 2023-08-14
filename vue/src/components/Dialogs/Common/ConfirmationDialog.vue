<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface ConfirmationDialog_Input {
    title?: string;
    body: string;
    confirmColor?: string;
    confirmIcon?: string;
    cancelIcon?: string;
    cancelColor?: string;

    responseCallback: (confirmed: boolean) => void;
}

const props = defineProps<{input: ConfirmationDialog_Input, events: DialogEventsInterface}>();

const used = ref(false);
const showDialog = ref(false);
const titleText = ref('');
const bodyText = ref('');
const confirmIcon = ref('fa fa-check');
const confirmColor = ref('red');
const cancelIcon = ref('fa fa-circle-xmark');
const cancelColor = ref('grey');

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
    titleText.value = props.input.title ?? 'Confirm';
    confirmIcon.value = props.input.confirmIcon ?? 'fa fa-check';
    confirmColor.value = props.input.confirmColor ?? 'green';
    cancelIcon.value = props.input.cancelIcon ?? 'fa fa-circle-xmark';
    cancelColor.value = props.input.cancelColor ?? 'grey';
    bodyText.value = props.input.body;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onYesBtnClicked() {
    props.input.responseCallback(true);
    close();
}

function onNoBtnClicked() {
    props.input.responseCallback(false);
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        ref="dialog"
        persistent
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title >{{ titleText }}</v-card-title>
            <v-divider/>
            <v-card-text v-html="bodyText">

            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    text
                    variant="tonal"
                    :prepend-icon="cancelIcon"
                    :color="cancelColor"
                    @click="onNoBtnClicked">
                    No
                </v-btn>
                <v-btn
                    flat
                    variant="tonal"
                    :prepend-icon="confirmIcon"
                    :color="confirmColor"
                    @click="onYesBtnClicked">
                    Yes
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
