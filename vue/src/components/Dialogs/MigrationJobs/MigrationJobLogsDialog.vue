<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {MigrationJob} from "@/core/services/Deploy/models";
import KubernetesLogList from "@/components/Modules/KubernetesLogs/List/KubernetesLogList.vue";

export interface MigrationJobLogsDialog_Input {
    migrationJob: MigrationJob;
}

const props = defineProps<{ input: MigrationJobLogsDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);
const kubernetesLogList = ref<InstanceType<typeof KubernetesLogList> | null>(null);

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

function onReloadBtnClicked() {
    kubernetesLogList?.value?.reload();
}

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
                    <span class="my-auto">Migration Job Kubernetes Logs</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.migrationJob.deployment?.name }}.{{ props.input.migrationJob.deployment?.namespace }}</v-chip>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <KubernetesLogList
                    ref="kubernetesLogList"
                    :show-header="false"
                    :namespace="props.input.migrationJob.deployment?.namespace ?? ''"
                    :app="props.input.migrationJob.deployment?.name ?? ''"
                    role="migration"/>
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
