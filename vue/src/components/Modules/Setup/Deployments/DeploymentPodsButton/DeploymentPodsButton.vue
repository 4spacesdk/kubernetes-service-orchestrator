<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesPod} from "@/core/services/Deploy/Api";
import type {DeploymentMetricsResponse} from "@/core/services/Deploy/Api";
import {barColor, cpuText, memoryText, shareOfLimit} from "@/helpers/Metrics";
import DateView from "@/components/Modules/Common/DateView.vue";
import bus from "@/plugins/bus";

const props = defineProps<{
    deployment: Deployment;
    app?: string;
    role?: string;
}>();

interface PodOption {
    pod: KubernetesPod,
    statusColor: string,
}

const isLoading = ref(false);
const pods = ref<PodOption[]>([]);

/**
 * What the pods are using right now - `kubectl top` in the menu that lists them. Beside what one
 * pod was given, because a number on its own says nothing: half a core is a lot for a form
 * handler and nothing for an importer.
 */
const metrics = ref<DeploymentMetricsResponse>();

function usageOf(pod: KubernetesPod) {
    return (metrics.value?.pods ?? []).filter(row => row.pod === pod.pod && row.container === pod.container);
}


onMounted(() => {
    // Its own request: the pods are what the menu is for, and the numbers are worth waiting a
    // moment longer for rather than holding the list back.
    Api.deployments().getMetricsGetById(props.deployment.id!).find(response => {
        metrics.value = response[0];
    });

    isLoading.value = true;
    Api.kubernetes().getPodsGetByNamespace(props.deployment.namespace!)
        .app(props.app ?? '')
        .role(props.role ?? '')
        .find(response => {
            pods.value = response
                .sort((a, b) => new Date(b.created!).getTime() - new Date(a.created!).getTime())
                .map(pod => {
                    let statusColor = 'grey';
                    switch (pod.status) {
                        case 'Running':
                            statusColor = 'warning';
                            break;
                        case 'Succeeded':
                            statusColor = 'success';
                            break;
                        case 'Failed':
                            statusColor = 'error';
                            break;
                    }
                    return {
                        pod: pod,
                        statusColor: statusColor
                    }
                });
            render();
        });
});

function render() {
    isLoading.value = false;
}

function onShowLogsBtnClicked(item: PodOption) {
    bus.emit('deploymentLogs', {
        deployment: props.deployment,
        preselectedPodName: item.pod.pod,
        preselectedContainerName: item.pod.container,
    });
}

/** Every pod at once, followed in one request - see `DeploymentLogs` in the backend. */
function onShowAllLogsBtnClicked() {
    bus.emit('deploymentLogs', {
        deployment: props.deployment,
    });
}

function onOpenTerminalBtnClicked(item: PodOption) {
    bus.emit('podTerminal', {
        deployment: props.deployment,
        pod: item.pod,
    });
}

</script>

<template>
    <div
        class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">

            <v-progress-linear v-if="isLoading"
                               color="primary"
                               indeterminate></v-progress-linear>
            <!-- What the deployment is using altogether, and what one pod was given. The total is
                 across the pods; the limit is per pod, so they are labelled rather than added. -->
            <div v-if="!isLoading && metrics?.available" class="d-flex flex-wrap align-center ga-4 px-4 py-2 totals">
                <span>
                    <v-icon size="x-small" class="me-1">fa fa-microchip</v-icon>
                    {{ cpuText(metrics.cpu_millicores) }} cpu
                    <span class="of-limit" v-if="metrics.cpu_limit">of {{ cpuText(metrics.cpu_limit) }} per pod</span>
                </span>
                <span>
                    <v-icon size="x-small" class="me-1">fa fa-memory</v-icon>
                    {{ memoryText(metrics.memory_bytes) }}
                    <span class="of-limit" v-if="metrics.memory_limit_bytes">of {{ memoryText(metrics.memory_limit_bytes) }} per pod</span>
                </span>
                <span class="of-limit ms-auto" v-if="metrics.window">measured over {{ metrics.window }}</span>
            </div>

            <div v-else-if="!isLoading && metrics && !metrics.available" class="px-4 py-2 totals of-limit">
                No cpu or memory: this cluster has no metrics-server, or kso may not read it
                <v-tooltip activator="parent" location="bottom" max-width="420">{{ metrics.reason }}</v-tooltip>
            </div>

            <v-list
                v-if="!isLoading"
                class="list-items">
                <v-list-item
                    v-if="pods.length"
                    @click="onShowAllLogsBtnClicked"
                >
                    <v-list-item-title>
                        <div class="d-flex">
                            <v-icon size="small" class="me-2 my-auto">fa fa-layer-group</v-icon>
                            <span class="my-auto">Logs from all pods, as one</span>
                        </div>
                    </v-list-item-title>
                </v-list-item>

                <v-divider v-if="pods.length" class="my-1"/>

                <v-list-item
                    v-for="pod in pods"
                >
                    <v-list-item-title>
                        <!-- Wraps where it has to - a phone - rather than pushing the buttons
                             off the edge. -->
                        <div class="d-flex flex-wrap pod-row">
                            <div
                                class="my-auto"
                                style="width: 100px;">
                                <v-chip
                                    :color="pod.statusColor"
                                    size="small">{{ pod.pod.status }}
                                </v-chip>
                            </div>

                            <span class="my-auto pod-name">{{ pod.pod.pod }}.{{ pod.pod.container }}</span>

                            <div class="d-flex ga-3 ml-auto pl-4 my-auto usage" v-for="usage in usageOf(pod.pod)" :key="usage.pod">
                                <span class="d-flex align-center ga-1">
                                    {{ cpuText(usage.cpu_millicores) }}
                                    <v-progress-linear
                                        v-if="shareOfLimit(usage.cpu_millicores, metrics?.cpu_limit) !== null"
                                        :model-value="shareOfLimit(usage.cpu_millicores, metrics?.cpu_limit) ?? 0"
                                        :color="barColor(shareOfLimit(usage.cpu_millicores, metrics?.cpu_limit))"
                                        height="4" rounded class="bar"/>
                                </span>
                                <span class="d-flex align-center ga-1">
                                    {{ memoryText(usage.memory_bytes) }}
                                    <v-progress-linear
                                        v-if="shareOfLimit(usage.memory_bytes, metrics?.memory_limit_bytes) !== null"
                                        :model-value="shareOfLimit(usage.memory_bytes, metrics?.memory_limit_bytes) ?? 0"
                                        :color="barColor(shareOfLimit(usage.memory_bytes, metrics?.memory_limit_bytes))"
                                        height="4" rounded class="bar"/>
                                </span>
                            </div>

                            <DateView
                                class="pl-4 my-auto"
                                :date-string="pod.pod.created"/>

                            <div class="d-flex justify-end gap-1 ml-2">
                                <v-btn
                                    variant="plain" color="primary" size="small" icon
                                    @click="onShowLogsBtnClicked(pod)">
                                    <v-icon>fa fa-rectangle-list</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Logs</v-tooltip>
                                </v-btn>

                                <v-btn
                                    variant="plain" color="primary" size="small" icon
                                    @click="onOpenTerminalBtnClicked(pod)">
                                    <v-icon>fa fa-terminal</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Terminal</v-tooltip>
                                </v-btn>
                            </div>
                        </div>
                    </v-list-item-title>
                </v-list-item>
            </v-list>
        </v-card>
    </div>
</template>

<style scoped>
.totals {
    font-size: 12px;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.of-limit {
    opacity: 0.6;
}

.usage {
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.bar {
    width: 48px;
}

.pod-row {
    row-gap: 4px;
}

.pod-name {
    flex: 1 1 160px;
    min-width: 0;
    white-space: normal;
    overflow-wrap: anywhere;
}

.list-wrapper {
    min-width: 260px;
}

.v-list-item {
    min-height: unset;
}

.v-list-item-title {
    font-size: 11px !important;
}
.v-progress-circular {
    margin: 1rem;
}
</style>
