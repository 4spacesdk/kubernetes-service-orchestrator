<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {useRouter} from "vue-router";
import moment from "moment";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesClusterHealthCounts, KubernetesClusterHealthResponse, KubernetesClusterNode} from "@/core/services/Deploy/Api";
import {HealthStatusTypes} from "@/constants";
import {barColor, cpuText, memoryText, shareOfLimit} from "@/helpers/Metrics";
import HealthChip from "@/components/Modules/Common/HealthChip.vue";

/**
 * The window behind the status bar's dot: the cluster's nodes - what explains a pod that will not
 * be scheduled - how kso's deployments and workspaces are doing, and whether kso's scheduler keeps
 * up. Read when it opens and on Reload, never polled.
 */
export interface ClusterHealthDialog_Input {
}

const props = defineProps<{ input: ClusterHealthDialog_Input, events: DialogEventsInterface }>();

const router = useRouter();

const showDialog = ref(false);
const isLoading = ref(false);
const error = ref<string>();
const health = ref<KubernetesClusterHealthResponse>();

/** Worst first, as the lists sort; the ones with none last. */
const healthOrder = [
    HealthStatusTypes.Degraded, HealthStatusTypes.Missing, HealthStatusTypes.Progressing,
    HealthStatusTypes.Unknown, HealthStatusTypes.Healthy, HealthStatusTypes.Suspended,
];

const counts = computed(() => [
    {title: 'Deployments', route: 'Deployments', counts: countsOf(health.value?.deployments)},
    {title: 'Workspaces', route: 'Workspaces', counts: countsOf(health.value?.workspaces)},
]);

function countsOf(byHealth?: KubernetesClusterHealthCounts): { health: string, count: number }[] {
    const map = (byHealth ?? {}) as Record<string, number>;
    return healthOrder
        .filter(value => map[value])
        .map(value => ({health: value, count: map[value]}));
}

onMounted(() => {
    showDialog.value = true;
    load();
});

function load() {
    isLoading.value = true;
    error.value = undefined;
    const api = Api.kubernetes().clusterHealthGet();
    api.setErrorHandler(response => {
        error.value = response.error ?? 'The cluster could not be read';
        isLoading.value = false;
        return false;
    });
    api.find(response => {
        health.value = response[0];
        isLoading.value = false;
    });
}

/** Whose each namespace is - see `ClusterHealth::Namespaces()` in the backend. */
const namespaceGroups = computed(() => [
    {owner: 'kso', title: 'This kso'},
    {owner: 'other', title: 'Not kso\'s'},
    {owner: 'theirs', title: 'Another kso\'s'},
    {owner: 'kubernetes', title: 'Kubernetes'},
].map(group => ({...group, namespaces: (health.value?.namespaces ?? []).filter(n => n.owner == group.owner)}))
    .filter(group => group.namespaces.length));

function openWorkspace(id: number) {
    close();
    router.push({name: 'WorkspaceById', params: {id}});
}

function age(seconds?: number): string {
    return seconds === undefined || seconds === null ? '-' : moment.duration(seconds, 'seconds').humanize();
}

function share(node: KubernetesClusterNode, resource: 'cpu' | 'memory'): number | null {
    return resource == 'cpu'
        ? shareOfLimit(node.cpu_requested, node.cpu_allocatable)
        : shareOfLimit(node.memory_requested, node.memory_allocatable);
}

function openList(route: string, value: string) {
    close();
    router.push({name: route, query: {health: [value]}});
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}
</script>

<template>
    <v-dialog
        v-model="showDialog"
        max-width="1100"
        scrollable
        @click:outside="close"
        @keydown.esc="close">
        <v-card :loading="isLoading">
            <v-card-title class="d-flex align-center">
                <span>Cluster</span>
                <v-spacer/>
                <v-btn
                    variant="text"
                    prepend-icon="fa fa-refresh"
                    :loading="isLoading"
                    @click="load">
                    Reload
                </v-btn>
            </v-card-title>
            <v-divider/>

            <v-card-text>
                <div v-if="error" class="text-error inset">{{ error }}</div>

                <template v-if="health">
                    <h3 class="heading">Nodes</h3>
                    <div
                        v-if="!health.metrics_available"
                        class="text-medium-emphasis mb-2 note inset">
                        No usage: metrics-server did not answer. Requested is what the pods asked for.
                    </div>
                    <v-table density="compact" class="nodes">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Ready</th>
                            <th>Version</th>
                            <th>Age</th>
                            <th>Pods</th>
                            <th>CPU requested</th>
                            <th>Memory requested</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="node in health.nodes" :key="node.name">
                            <td>
                                <div>{{ node.name }}</div>
                                <div class="d-flex flex-wrap ga-1 mt-1">
                                    <v-chip v-for="role in node.roles" :key="role" size="x-small" label>{{ role }}</v-chip>
                                    <v-chip v-if="node.unschedulable" size="x-small" label color="warning">cordoned</v-chip>
                                    <v-chip v-for="pressure in node.pressures" :key="pressure" size="x-small" label color="error">{{ pressure }}</v-chip>
                                </div>
                            </td>
                            <td>
                                <v-chip
                                    size="small"
                                    label
                                    variant="tonal"
                                    :color="node.ready ? 'success' : 'error'">
                                    {{ node.ready ? 'Ready' : 'Not ready' }}
                                    <v-tooltip v-if="node.ready_reason" activator="parent" location="top">{{ node.ready_reason }}</v-tooltip>
                                </v-chip>
                            </td>
                            <td>{{ node.kubelet_version }}</td>
                            <td>{{ age(node.age_seconds) }}</td>
                            <td>{{ node.pods }}<span class="of"> / {{ node.pods_allocatable ?? '-' }}</span></td>
                            <td v-for="resource in (['cpu', 'memory'] as const)" :key="resource" class="usage">
                                <div>
                                    {{ resource == 'cpu' ? cpuText(node.cpu_requested) : memoryText(node.memory_requested) }}
                                    <span class="of">of {{ resource == 'cpu' ? cpuText(node.cpu_allocatable) : memoryText(node.memory_allocatable) }}</span>
                                </div>
                                <v-progress-linear
                                    :model-value="share(node, resource) ?? 0"
                                    :color="barColor(share(node, resource))"
                                    height="4"
                                    rounded/>
                                <div v-if="health.metrics_available" class="of">
                                    using {{ resource == 'cpu' ? cpuText(node.cpu_usage) : memoryText(node.memory_usage) }} now
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </v-table>

                    <h3 class="heading">Health</h3>
                    <div v-for="row in counts" :key="row.title" class="d-flex align-center flex-wrap ga-2 mb-2 inset">
                        <span class="row-title">{{ row.title }}</span>
                        <span v-if="!row.counts.length" class="text-medium-emphasis">None with a health yet</span>
                        <button
                            v-for="item in row.counts"
                            :key="item.health"
                            class="count"
                            @click="openList(row.route, item.health)">
                            <HealthChip :health="item.health"/>
                            <span class="ml-1">{{ item.count }}</span>
                        </button>
                    </div>

                    <h3 class="heading">Namespaces</h3>
                    <div v-for="group in namespaceGroups" :key="group.owner" class="d-flex align-start flex-wrap ga-2 mb-2 inset">
                        <span class="row-title">{{ group.title }}</span>
                        <div class="d-flex flex-wrap ga-1 namespaces">
                            <v-chip
                                v-for="namespace in group.namespaces"
                                :key="namespace.name"
                                size="small"
                                label
                                :variant="namespace.workspace_id ? 'tonal' : 'outlined'"
                                :color="namespace.workspace_id ? 'secondary' : undefined"
                                @click="namespace.workspace_id && openWorkspace(namespace.workspace_id)">
                                {{ namespace.name }}
                                <span class="of ml-1">{{ namespace.pods }} pods</span>
                                <v-tooltip activator="parent" location="top">
                                    <template v-if="namespace.workspace">Workspace {{ namespace.workspace }} · </template>{{ age(namespace.age_seconds) }} old
                                </v-tooltip>
                            </v-chip>
                        </div>
                    </div>

                    <h3 class="heading">Scheduler</h3>
                    <div class="d-flex align-center ga-2 inset">
                        <v-icon
                            size="small"
                            :color="health.scheduler?.behind ? 'warning' : 'success'">
                            {{ health.scheduler?.behind ? 'fa fa-triangle-exclamation' : 'fa fa-circle-check' }}
                        </v-icon>
                        <span v-if="health.scheduler?.last_run">
                            Last ran {{ moment(health.scheduler.last_run).fromNow() }}<template v-if="health.scheduler.behind"> - it runs every minute, so it is behind or stopped</template>
                        </span>
                        <span v-else>Has never run - is the cron container running?</span>
                    </div>
                </template>
            </v-card-text>

            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="close">
                    Close
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
/* The table runs edge to edge, as in every dialog; the rest keeps the dialog's margin. */
.heading,
.inset {
    padding: 0 16px;
}

.heading {
    margin: 16px 0 8px;
    font-size: 13px;
    font-weight: 600;
}

.heading:first-of-type {
    margin-top: 0;
}

.note {
    font-size: 12px;
}

.nodes td {
    vertical-align: top;
    padding-top: 6px !important;
    padding-bottom: 6px !important;
}

.usage {
    min-width: 150px;
}

.of {
    font-size: 11px;
    opacity: 0.6;
}

.row-title {
    width: 100px;
    font-size: 12px;
    opacity: 0.7;
}

.namespaces {
    flex: 1 1 0;
    min-width: 0;
}

.count {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}
</style>
