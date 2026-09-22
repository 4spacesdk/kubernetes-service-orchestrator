<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {WorkloadTypes} from "@/constants";
import type {DeploymentMetricsResponse} from "@/core/services/Deploy/Api";
import {cpuText, memoryText, shareOfLimit} from "@/helpers/Metrics";
import {useDialogSave} from "@/composables/useDialogSave";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    deployment: Deployment
}>();

const {isSaving, save} = useDialogSave();

const isLoading = ref(false);
const cpuLimit = ref<number>();
const cpuRequest = ref<number>();
const memoryLimit = ref<number>();
const memoryRequest = ref<number>();
const replicas = ref<number>();
const knativeConcurrencyLimitSoft = ref<number>();
const knativeConcurrencyLimitHard = ref<number>();

const isKNativeService = computed(() => props.deployment.deployment_specification?.workload_type == WorkloadTypes.KNativeService);

const {markSaved} = useUnsavedChanges(() => [
    cpuLimit.value,
    cpuRequest.value,
    memoryLimit.value,
    memoryRequest.value,
    replicas.value,
    knativeConcurrencyLimitSoft.value,
    knativeConcurrencyLimitHard.value,
]);

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
    render();

    Api.deployments().getMetricsGetById(props.deployment.id!).find(response => {
        metrics.value = response[0];
    });
});

function render() {
    isLoading.value = true;
    Api.deployments().getById(props.deployment.id!)
        .find(response => {
            const deployment = response[0];
            cpuLimit.value = deployment?.cpu_limit ?? 0;
            cpuRequest.value = deployment?.cpu_request ?? 0;
            memoryLimit.value = deployment?.memory_limit ?? 0;
            memoryRequest.value = deployment?.memory_request ?? 0;
            replicas.value = deployment?.replicas ?? 0;
            knativeConcurrencyLimitSoft.value = deployment?.knative_concurrency_limit_soft ?? 0;
            knativeConcurrencyLimitHard.value = deployment?.knative_concurrency_limit_hard ?? 0;
            isLoading.value = false;
            markSaved();
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSave() {
    const api = Api.deployments().updateResourceManagementPutById(props.deployment.id!)
        .cpuLimit(cpuLimit.value!)
        .cpuRequest(cpuRequest.value!)
        .memoryLimit(memoryLimit.value!)
        .memoryRequest(memoryRequest.value!)
        .replicas(replicas.value!)
        .knativeConcurrencyLimitSoft(knativeConcurrencyLimitSoft.value!)
        .knativeConcurrencyLimitHard(knativeConcurrencyLimitHard.value!);
    save(api, null, newItem => {
        bus.emit('deploymentSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
        render();
    });
}

// </editor-fold>

</script>

<template>
    <page-section
        title="Resource Management"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <div class="section-form">
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
                    v-if="!isKNativeService"
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
                    v-if="isKNativeService"
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
                    v-if="isKNativeService"
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
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}

/* Under the fields they are about, in the section's own quiet voice - the same as a hint. */
.measured {
    font-size: 12px;
    opacity: 0.75;
    padding-top: 0;
    padding-bottom: 12px;
}
</style>
