<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import type {KubernetesPod} from "@/core/services/Deploy/Api";
import PodTerminal from "@/components/Modules/PodTerminal/PodTerminal.vue";

export interface PodTerminalDialog_Input {
    pod: KubernetesPod;
}

const props = defineProps<{ input: PodTerminalDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
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
        height="100vh"
        width="100vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading">
            <v-card-title>
                <div class="d-flex">
                    <span class="my-auto">Pod Terminal</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.pod.name }}</v-chip>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <PodTerminal
                    :pod="props.input.pod"/>
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
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
