<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesPod} from "@/core/services/Deploy/Api";
import PodTerminal from "@/components/Modules/PodTerminal/PodTerminal.vue";
import type {QuickCommand} from "@/components/Modules/PodTerminal/PodTerminal.vue";
import {Deployment} from "@/core/services/Deploy/models";

export interface PodTerminalDialog_Input {
    deployment: Deployment;
    pod: KubernetesPod;
}

const props = defineProps<{ input: PodTerminalDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);
const quickCommands = ref<QuickCommand[]>([]);

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

    Api.deploymentSpecifications().get()
        .where('id', props.input.deployment.deployment_specification_id)
        .include('deployment_specification_post_command')
        .include('deployment_specification_quick_command')
        .find(response => {
            quickCommands.value = [
                ...response[0].deployment_specification_post_commands?.map(postCommand => {
                    return {
                        name: postCommand.name,
                        command: postCommand.command,
                    };
                }) ?? [],
                ...response[0].deployment_specification_quick_commands?.map(quickCommand => {
                    return {
                        name: quickCommand.name,
                        command: quickCommand.command,
                    };
                }) ?? [],
            ];
        })
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
                    :quick-command-items="quickCommands"
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
