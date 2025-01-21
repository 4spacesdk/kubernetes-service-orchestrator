<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesPod} from "@/core/services/Deploy/Api";
import type {KubernetesLogEntry} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";
import {ChangeEvent} from "@/services/Wamp/ChangeEvent";
import {ApiRequest} from "@/core/services/ApiHelpers/ApiRequest";
import bus from "@/plugins/bus";

const props = defineProps<{
    namespace?: string;
    app?: string;
    role?: string;
    preselectedPodName?: string;
    preselectedContainerName?: string;

    showHeader: boolean;
}>();

defineExpose({
    reload
})

interface Row {
    date: Date,
    line: string;
}

interface PodOption {
    pod: KubernetesPod,
    statusColor: string,
}

const isPodsLoading = ref(false);
const isLogsLoading = ref(false);
const isWatching = ref(false);
const pods = ref<PodOption[]>([]);
const activePod = ref<KubernetesPod | null>(null);
const previousPod = ref<KubernetesPod | null>(null);
const logs = ref<Row[]>([]);
const container = ref<HTMLPreElement>();
const showWatcherFinishedMessage = ref(false);

// <editor-fold desc="Functions">

onMounted(() => {
    reload();

    watch(activePod, getLogs);
});

onUnmounted(() => {
    watchWampSubscription.value?.unsubscribe();
    watchApiRequest.value?.cancel();
});

function reload() {
    if (activePod.value) {
        previousPod.value = activePod.value;
    }

    activePod.value = null
    isPodsLoading.value = true;
    showWatcherFinishedMessage.value = false;
    watchApiRequest.value?.cancel();
    watchWampSubscription.value?.unsubscribe();

    Api.kubernetes().getPodsGetByNamespace(props.namespace!)
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
                            statusColor = 'green';
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
            isPodsLoading.value = false;

            if (pods.value.length) {
                const updatedPod = pods.value.find(p => p.pod.pod == activePod?.value?.pod && p.pod.container == activePod?.value?.container);
                if (previousPod && updatedPod) {
                    activePod.value = updatedPod.pod;
                } else {

                    // Look for preselected pod
                    if (props.preselectedPodName) {
                        const preselectedPod = pods.value.find(p => p.pod.pod == props.preselectedPodName && p.pod.container == props.preselectedContainerName);
                        if (preselectedPod) {
                            activePod.value = preselectedPod.pod;
                        }
                    }

                    // Default to first pod
                    if (!activePod.value) {
                        activePod.value = pods.value[0].pod;
                    }
                }
            }
        });
}

const watchWampSubscription = ref<WampSubscription>();
const watchApiRequest = ref<ApiRequest>();

function getLogs() {
    if (activePod.value === null) {
        return;
    }

    isLogsLoading.value = true;
    logs.value = [];
    const api = Api.kubernetes().getLogsGetByNamespaceByPodByContainer(props.namespace!, activePod.value!.pod!, activePod.value!.container!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('json', {
                title: `Failed to fetch log`,
                body: JSON.parse(response.error),
            });
            isLogsLoading.value = false;
        }
        return false;
    });
    api.find(response => {
        logs.value = response.map(entry => {
            return {
                date: new Date(entry.date!),
                line: entry.line!,
            }
        });
        isLogsLoading.value = false;
        setTimeout(() => container?.value?.scrollIntoView(false));

        // Cancel previous watchers
        watchWampSubscription.value?.unsubscribe();

        watchWampSubscription.value = WampService.subscribe(
            Events.KubernetesPod_Logs_Watch(activePod.value!.pod!, activePod.value!.container!),
            data => {
                const changeEvent = new ChangeEvent<KubernetesLogEntry[]>(data.previous, data.next);
                changeEvent.next.forEach(entry => {
                    logs.value.push({
                        date: new Date(entry.date!),
                        line: entry.line!,
                    });
                });
                setTimeout(() => container?.value?.scrollIntoView(false));
            }
        );

        startWatching();
    });
}

function startWatching() {
    watchApiRequest.value?.cancel();
    setTimeout(() => { // Let it finish before starting new request
        isWatching.value = true;
        showWatcherFinishedMessage.value = false;
        watchApiRequest.value = Api.kubernetes().watchLogsPutByNamespaceByPodByContainer(props.namespace!, activePod.value!.pod!, activePod.value!.container!)
            .save(null, () => {
                isWatching.value = false;
                showWatcherFinishedMessage.value = true;
            });
    });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onPodListItemClicked(item: PodOption) {
    activePod.value = item.pod;
}

// </editor-fold>

</script>

<template>

    <div class="d-flex flex-column w-100 h-100 tab-wrapper">
        <v-toolbar
            v-if="props.showHeader"
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>Kubernetes Logs</v-toolbar-title>
            <v-spacer></v-spacer>
        </v-toolbar>

        <v-toolbar bg-color="secondary"
                   density="compact"
                   flat
                   color="blue-grey lighten-5"
                   class="d-flex w-100 flex-column"
        >
            <div class="d-flex flex-grow-1 px-5 ga-3">
                <div class="d-flex flex-column">
                    <span>Pod name</span>
                    <strong>{{ activePod?.pod || 'Loading...' }}</strong>
                </div>
                <div class="d-flex flex-column">
                    <span>Container name</span>
                    <strong>{{ activePod?.container }}</strong>
                </div>
                <div class="d-flex flex-column">
                    <span>Pod created</span>
                    <DateView :date-string="activePod?.created"/>
                </div>
                <div class="d-flex flex-column">
                    <span>Pod status</span>
                    <strong>{{ activePod?.status }}</strong>
                </div>
            </div>

            <v-menu class="mx-auto">

                <template v-slot:activator="{ props }">
                    <v-btn
                        :loading="isPodsLoading"
                        v-bind="props"
                        variant="outlined" color="white" size="small"
                    >
                        {{ activePod?.pod }} - {{ activePod?.container }}
                    </v-btn>
                </template>
                <v-list>
                    <v-list-item
                        v-for="(pod, i) in pods" :key="i"
                        :value="pod.pod"
                        @click="onPodListItemClicked(pod)"
                    >
                        <v-list-item-title>
                            <div class="d-flex">
                                <div
                                    style="width: 100px;">
                                    <v-chip
                                        :color="pod.statusColor"
                                        size="small">{{ pod.pod.status }}
                                    </v-chip>
                                </div>
                                <span class="my-auto">{{ pod.pod.pod }} - {{ pod.pod.container }}</span>

                                <DateView
                                    class="ml-auto pl-4 my-auto"
                                    :date-string="pod.pod.created"/>
                            </div>
                        </v-list-item-title>
                    </v-list-item>
                </v-list>

            </v-menu>

        </v-toolbar>

        <v-progress-linear
            v-if="isLogsLoading || isPodsLoading"
            indeterminate/>

        <code
            ref="container">
            <span
                class="d-flex flex-nowrap"
                v-for="(line, i) in logs" :key="i">
                <DateView
                    class="date-view"
                    text-format="DD/MM-YY HH:mm:ss"
                    :date="line.date"/>
                <span class="code-line">{{ line.line }}</span>
            </span>
        </code>

        <div class="overlay">
            <v-chip
                v-if="isWatching"
                color="green">
                <v-icon class="me-1">fas fa-circle-play</v-icon>
                <span>Watching...</span>
            </v-chip>

            <v-chip
                @click="reload"
                v-if="showWatcherFinishedMessage"
                style="cursor: pointer;"
                color="warning">
                <v-icon class="me-1">fas fa-circle-stop</v-icon>
                <span>Watcher closed down. Reload to check for new containers</span>
            </v-chip>
        </div>
    </div>

</template>

<style scoped>
.overlay {
    position: fixed;
    z-index: 100;
    bottom: 4rem;
    right: 1rem;
    width: fit-content;

}

.date-view {
    width: 176px;
    overflow: hidden;
    color: #b0b2b2;
    flex-shrink: 0;
    background: rgb(225, 231, 233, .8);
    filter: dropShadow(0px 2px 8px rgba(0, 0, 0, 0.8));
    border-right: 1px solid rgba(0, 0, 0, 0.1);
    margin-right: .5rem;
    position: sticky;
    left: -16px;
    padding-left: 10px;
}

code {
    flex-grow: 1;
    overflow-x: auto;
}

.code-line {

    white-space: pre;
    display: block;

    /*overflow-x: auto;*/
}
</style>
