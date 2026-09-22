<script setup lang="ts">
import { useDialogSave } from "@/composables/useDialogSave";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {WorkloadTypes} from "@/constants";
import type {DeploymentMetricsResponse} from "@/core/services/Deploy/Api";
import {cpuText, memoryText, shareOfLimit} from "@/helpers/Metrics";

export interface DeploymentUpdateResourceManagementDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateResourceManagementDialog_Input, events: DialogEventsInterface }>();

const { isSaving, save } = useDialogSave();

const used = ref(false);
const showDialog = ref(false);

const cpuLimit = ref<number>();
const cpuRequest = ref<number>();
const memoryLimit = ref<number>();
const memoryRequest = ref<number>();
const replicas = ref<number>();
const knativeConcurrencyLimitSoft = ref<number>();
const knativeConcurrencyLimitHard = ref<number>();

/**
 * What the pods are using while these numbers are being decided. A limit is a guess until
 * somebody puts it beside a measurement: 954Mi looks reasonable until the pods turn out to use 64.
 */
const metrics = ref<DeploymentMetricsResponse>();

/** Per pod, because that is what a request and a limit are - not the deployment's total. */
const cpuPerPod = computed(() => (metrics.value?.pods ?? []).map(pod => pod.cpu_millicores ?? 0));
const memoryPerPod = computed(() => (metrics.value?.pods ?? []).map(pod => pod.memory_bytes ?? 0));

function usageText(values: number[], format: (value: number) => string): string {
    if (!values.length) {
        return '';
    }
    return values.map(format).join(', ');
}

/** The pod nearest its limit is the one that decides whether the limit is too low. */
function highestShare(values: number[], limit?: number): number | null {
    return values.length ? shareOfLimit(Math.max(...values), limit) : null;
}

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();

    Api.deployments().getMetricsGetById(props.input.deployment.id!).find(response => {
        metrics.value = response[0];
    });
});

onUnmounted(() => {
});

function render() {
    cpuLimit.value = props.input.deployment.cpu_limit ?? 0;
    cpuRequest.value = props.input.deployment.cpu_request ?? 0;
    memoryLimit.value = props.input.deployment.memory_limit ?? 0;
    memoryRequest.value = props.input.deployment.memory_request ?? 0;
    replicas.value = props.input.deployment.replicas ?? 0;
    knativeConcurrencyLimitSoft.value = props.input.deployment.knative_concurrency_limit_soft ?? 0;
    knativeConcurrencyLimitHard.value = props.input.deployment.knative_concurrency_limit_hard ?? 0;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = Api.deployments().updateResourceManagementPutById(props.input.deployment.id!)
        .cpuLimit(cpuLimit.value!)
        .cpuRequest(cpuRequest.value!)
        .memoryLimit(memoryLimit.value!)
        .memoryRequest(memoryRequest.value!)
        .replicas(replicas.value!)
        .knativeConcurrencyLimitSoft(knativeConcurrencyLimitSoft.value!)
        .knativeConcurrencyLimitHard(knativeConcurrencyLimitHard.value!);
    save(api, null, newItem => {
        props.input.deployment.cpu_limit = cpuLimit.value!;
        props.input.deployment.cpu_request = cpuRequest.value!;
        props.input.deployment.memory_limit = memoryLimit.value!;
        props.input.deployment.memory_request = memoryRequest.value!;
        props.input.deployment.replicas = replicas.value!;
        props.input.deployment.knative_concurrency_limit_soft = knativeConcurrencyLimitSoft.value!;
        props.input.deployment.knative_concurrency_limit_hard = knativeConcurrencyLimitHard.value!;
        bus.emit('deploymentSaved', newItem);
        close();
    });
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
            <v-card-title>Deployment</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="6">
                        <v-text-field
                            v-model.number="cpuRequest"
                            type="number"
                            variant="outlined"
                            hint="100 = 0.1 CPU"
                            persistent-hint
                            label="CPU Request"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model.number="cpuLimit"
                            type="number"
                            variant="outlined"
                            hint="500 = 0.5 CPU"
                            persistent-hint
                            label="CPU Limit"/>
                    </v-col>

                    <v-col cols="12" v-if="metrics?.available && cpuPerPod.length" class="measured">
                        <v-icon size="x-small" class="me-1">fa fa-microchip</v-icon>
                        Using {{ usageText(cpuPerPod, cpuText) }} per pod right now
                        <span v-if="highestShare(cpuPerPod, metrics.cpu_limit) !== null">
                            - the busiest is {{ highestShare(cpuPerPod, metrics.cpu_limit) }}% of the limit
                        </span>
                    </v-col>

                    <v-col cols="6">
                        <v-text-field
                            v-model.number="memoryRequest"
                            type="number"
                            variant="outlined"
                            hint="190.73 = 200 MB"
                            persistent-hint
                            label="Memory Request"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model.number="memoryLimit"
                            type="number"
                            variant="outlined"
                            hint="953.67 = 1 GB"
                            persistent-hint
                            label="Memory Limit"/>
                    </v-col>

                    <v-col cols="12" v-if="metrics?.available && memoryPerPod.length" class="measured">
                        <v-icon size="x-small" class="me-1">fa fa-memory</v-icon>
                        Using {{ usageText(memoryPerPod, memoryText) }} per pod right now
                        <span v-if="highestShare(memoryPerPod, metrics.memory_limit_bytes) !== null">
                            - the busiest is {{ highestShare(memoryPerPod, metrics.memory_limit_bytes) }}% of the limit
                        </span>
                    </v-col>

                    <v-col cols="12" v-else-if="metrics && !metrics.available" class="measured">
                        No measurement: this cluster has no metrics-server, or kso may not read it
                    </v-col>

                    <v-col
                        v-if="props.input.deployment.deployment_specification?.workload_type !== WorkloadTypes.KNativeService"
                        cols="12"
                    >
                        <v-text-field
                            v-model.number="replicas"
                            type="number"
                            variant="outlined"
                            hint="0 = not running, 1 = testing purposes, 3 = production"
                            persistent-hint
                            label="Replicas"/>
                    </v-col>
                    <v-col
                        v-if="props.input.deployment.deployment_specification?.workload_type == WorkloadTypes.KNativeService"
                        cols="6"
                    >
                        <v-text-field
                            v-model.number="knativeConcurrencyLimitSoft"
                            type="number"
                            variant="outlined"
                            hint=""
                            persistent-hint
                            label="Container Concurrency Soft Limit"/>
                    </v-col>
                    <v-col
                        v-if="props.input.deployment.deployment_specification?.workload_type == WorkloadTypes.KNativeService"
                        cols="6"
                    >
                        <v-text-field
                            v-model.number="knativeConcurrencyLimitHard"
                            type="number"
                            variant="outlined"
                            hint=""
                            persistent-hint
                            label="Container Concurrency Hard Limit"/>
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
                    :loading="isSaving"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
/* Under the fields they are about, in the dialog's own quiet voice - the same as a hint. */
.measured {
    font-size: 12px;
    opacity: 0.75;
    padding-top: 0;
    padding-bottom: 12px;
}


</style>
