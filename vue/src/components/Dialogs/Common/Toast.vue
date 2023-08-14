<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface ToastDialog_Input {
    text: string;
    icon?: string;
    color?: string;
}

const props = defineProps<{input: ToastDialog_Input, events: DialogEventsInterface}>();

const used = ref(false);
const showDialog = ref(false);
const text = ref('');

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

function render() {
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
    <v-snackbar
        v-model="showDialog"
        @update:model-value="$event ? '' : close()">

        <v-chip variant="text" :color="props.input.color" class="d-flex align-center">
            <v-icon v-if="props.input.icon" class=" mr-1">{{props.input.icon}}</v-icon>
            <span class="text-capitalize-first-letter">{{ props.input.text }}</span>
        </v-chip>

        <template v-slot:actions>
            <v-btn
                color="pink"
                variant="text"
                @click="onCloseBtnClicked"
            >
                Close
            </v-btn>
        </template>
    </v-snackbar>
</template>

<style scoped>

</style>
