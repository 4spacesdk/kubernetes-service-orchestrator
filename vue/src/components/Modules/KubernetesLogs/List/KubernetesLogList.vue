<script setup lang="ts">
import {computed, defineComponent, nextTick, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesPod} from "@/core/services/Deploy/Api";
import type {KubernetesLogEntry} from "@/core/services/Deploy/Api";
import type {DeploymentLogEntry} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import {PushSubscription} from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import {Events} from "@/services/Push/Events";
import {ChangeEvent} from "@/services/Push/ChangeEvent";
import {ApiRequest} from "@/core/services/ApiHelpers/ApiRequest";
import bus from "@/plugins/bus";

const props = defineProps<{
    namespace?: string;
    app?: string;
    role?: string;
    preselectedPodName?: string;
    preselectedContainerName?: string;

    /** Given, the list can read every pod of that deployment as one log - and starts there. */
    deploymentId?: number;

    showHeader: boolean;
}>();

defineExpose({
    reload
})

interface Row {
    date: Date,
    line: string;
    /** Only when reading every pod at once, where a line on its own says nothing about where from. */
    pod?: string;
}

interface PodOption {
    pod: KubernetesPod,
    statusColor: string,
}

/**
 * Every pod at once, in one request that follows them all - the view a rollout is watched in.
 * Off when somebody picks a single pod, which is still the right view for one container's log.
 */
const allPods = ref(!!props.deploymentId && !props.preselectedPodName);

/**
 * How many lines are kept in view. Every pod of a busy deployment at once fills a browser
 * quicker than one did, and the oldest lines are the ones already scrolled past.
 */
const MostLinesKept = 2000;

/**
 * The windows the backend accepts (`LogQuery::Windows`). "Last lines" is no window at all - the
 * hundred lines the api server hands back by default.
 */
const windowOptions = [
    {value: 0, title: 'Last lines'},
    {value: 900, title: 'Last 15 minutes'},
    {value: 3600, title: 'Last hour'},
    {value: 21600, title: 'Last 6 hours'},
];
const selectedWindow = ref(0);

/**
 * The container that ran before this one - what a crash left behind. There is nothing to follow
 * in a log that has already ended, so watching stops while this is on.
 */
const previousContainer = ref(false);

/**
 * Whether the view sticks to the end as lines come in. On to begin with, and from then on it is
 * the scrollbar that decides: scrolling up lets go, scrolling back to the bottom takes hold
 * again - the way a terminal, a chat and every other log viewer behaves.
 */
const follow = ref(true);

/** Pixels from the bottom that still count as the bottom - a stray pixel is not a decision. */
const AtBottomSlack = 24;

/**
 * What tells one pod from another: `klartboard-backend-59fbbb784c-84q7d` is the deployment's name
 * and then the part that differs, and the column is narrow enough that keeping the first would cut
 * off the second. The whole name is in the title attribute.
 */
function shortPodName(pod: string): string {
    const prefix = `${props.app ?? ''}-`;
    return props.app && pod.startsWith(prefix) ? pod.slice(prefix.length) : pod;
}

const search = ref('');
const shownLogs = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (!needle) {
        return logs.value;
    }
    return logs.value.filter(row => row.line.toLowerCase().includes(needle) || (row.pod ?? '').toLowerCase().includes(needle));
});
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
    watch(allPods, getLogs);
    watch(selectedWindow, getLogs);
    watch(previousContainer, getLogs);
});

onUnmounted(() => {
    watchPushSubscription.value?.unsubscribe();
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
    watchPushSubscription.value?.unsubscribe();

    // A deployment's pods by the deployment: a custom resource's are its operator's and carry
    // none of kso's labels - see `WorkloadPods` in the backend.
    const podsApi = props.deploymentId
        ? Api.deployments().getPodsGetById(props.deploymentId)
        : Api.kubernetes().getPodsGetByNamespace(props.namespace!)
            .app(props.app ?? '')
            .role(props.role ?? '');
    podsApi
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

const watchPushSubscription = ref<PushSubscription>();
const watchApiRequest = ref<ApiRequest>();

function getLogs() {
    if (allPods.value) {
        getDeploymentLogs();
        return;
    }
    if (activePod.value === null) {
        return;
    }

    isLogsLoading.value = true;
    logs.value = [];
    stopWatching();
    const api = Api.kubernetes().getLogsGetByNamespaceByPodByContainer(props.namespace!, activePod.value!.pod!, activePod.value!.container!);
    if (previousContainer.value) {
        api.previous(true);
    }
    if (selectedWindow.value) {
        api.sinceSeconds(selectedWindow.value);
    }
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
        // A view that has just been filled starts at the newest line, whatever it did before.
        follow.value = true;
        scrollToBottom();

        if (previousContainer.value) {
            return;
        }

        watchPushSubscription.value = PushService.subscribe(
            Events.KubernetesPod_Logs_Watch(activePod.value!.pod!, activePod.value!.container!),
            data => {
                const changeEvent = new ChangeEvent<KubernetesLogEntry[]>(data.previous, data.next);
                changeEvent.next.forEach(entry => {
                    logs.value.push({
                        date: new Date(entry.date!),
                        line: entry.line!,
                    });
                });
                if (logs.value.length > MostLinesKept) {
                    logs.value.splice(0, logs.value.length - MostLinesKept);
                }
                keepUp();
            }
        );

        startWatching();
    });
}

/**
 * The same three steps as below, against the deployment rather than one of its pods: the lines so
 * far, a subscription for what comes next, and the one request that follows every pod.
 */
/**
 * A log that has already ended has nothing to follow: the request is cancelled *and* the
 * subscription dropped. Cancelling alone leaves the process on the server publishing, and its
 * lines would arrive in the view of the crashed container - the one place they do not belong.
 */
function isAtBottom(): boolean {
    const element = container.value;
    if (!element) {
        return true;
    }
    return element.scrollHeight - element.scrollTop - element.clientHeight <= AtBottomSlack;
}

function scrollToBottom() {
    nextTick(() => {
        const element = container.value;
        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    });
}

/** After new lines: only when the reader has not scrolled away from the end. */
function keepUp() {
    if (follow.value) {
        scrollToBottom();
    }
}

/**
 * The reader moved the scrollbar. Scrolling done from here lands at the bottom and therefore
 * reads as following, which is what it is.
 */
function onLogScrolled() {
    follow.value = isAtBottom();
}

function onFollowClicked() {
    follow.value = !follow.value;
    if (follow.value) {
        scrollToBottom();
    }
}

function stopWatching() {
    watchApiRequest.value?.cancel();
    watchPushSubscription.value?.unsubscribe();
    watchPushSubscription.value = undefined;
    isWatching.value = false;
    showWatcherFinishedMessage.value = false;
}

function getDeploymentLogs() {
    isLogsLoading.value = true;
    logs.value = [];
    stopWatching();

    const api = Api.deployments().getLogsGetById(props.deploymentId!);
    if (previousContainer.value) {
        api.previous(true);
    }
    if (selectedWindow.value) {
        api.sinceSeconds(selectedWindow.value);
    }

    api.find(entries => {
        logs.value = entries.map(entry => ({
            date: new Date(entry.date!),
            line: entry.line!,
            pod: entry.pod!,
        }));
        isLogsLoading.value = false;
        follow.value = true;
        scrollToBottom();

        if (previousContainer.value) {
            return;
        }

        watchPushSubscription.value = PushService.subscribe(
            Events.Deployment_Logs_Watch(props.deploymentId!),
            data => {
                const changeEvent = new ChangeEvent<DeploymentLogEntry[]>(data.previous, data.next);
                changeEvent.next.forEach(entry => {
                    logs.value.push({
                        date: new Date(entry.date!),
                        line: entry.line!,
                        pod: entry.pod!,
                    });
                });
                if (logs.value.length > MostLinesKept) {
                    logs.value.splice(0, logs.value.length - MostLinesKept);
                }
                keepUp();
            }
        );

        setTimeout(() => {
            isWatching.value = true;
            showWatcherFinishedMessage.value = false;
            watchApiRequest.value = Api.deployments().watchLogsPutById(props.deploymentId!)
                .save(null, () => {
                    isWatching.value = false;
                    showWatcherFinishedMessage.value = true;
                });
        });
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
    allPods.value = false;
    activePod.value = item.pod;
}

function onAllPodsClicked() {
    allPods.value = true;
}

// </editor-fold>

</script>

<template>

    <div class="d-flex flex-column w-100 h-100 tab-wrapper">
        <v-toolbar
            v-if="props.showHeader"
            density="compact"
            flat
            color="toolbar"
            dark
        >
            <v-toolbar-title>Kubernetes Logs</v-toolbar-title>
            <v-spacer></v-spacer>
        </v-toolbar>

        <!-- Two rows, as on the lists: `v-toolbar` puts its content in a row of its own height,
             so the column has to be inside it. Its height follows the content, as the controls
             wrap wherever the log is narrow - beside a page's side menu too, not only on a phone. -->
        <v-toolbar density="compact"
                   flat
                   color="toolbar"
                   class="toolbar-wraps"
        >
            <div class="d-flex flex-column w-100 px-5 py-2 ga-2">
            <!-- Reading every pod at once, one pod's name, age and status is not the answer to
                 any question the header can be asked - so it says what is being read instead. -->
            <div v-if="allPods" class="d-flex flex-wrap ga-6 info">
                <div class="d-flex flex-column">
                    <span>Deployment</span>
                    <strong>{{ props.app }}</strong>
                </div>
                <div class="d-flex flex-column">
                    <span>Namespace</span>
                    <strong>{{ props.namespace }}</strong>
                </div>
                <div class="d-flex flex-column">
                    <span>Pods</span>
                    <strong>{{ pods.length }} read as one log</strong>
                </div>
            </div>

            <div v-else class="d-flex flex-wrap ga-6 info">
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

            <div class="d-flex flex-wrap ga-3 align-center controls">
            <v-text-field
                v-model="search"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search the lines below"
                clearable
                class="search"
            />

            <v-select
                v-model="selectedWindow"
                :items="windowOptions"
                item-value="value"
                item-title="title"
                density="compact"
                variant="outlined"
                hide-details
                class="window"
            />

            <v-btn
                :color="follow ? undefined : 'grey'"
                :variant="follow ? 'outlined' : 'text'"
                size="small"
                @click="onFollowClicked"
            >
                <v-icon size="small" class="me-1">{{ follow ? 'fa fa-angles-down' : 'fa fa-pause' }}</v-icon>
                {{ follow ? 'Following' : 'Paused' }}
                <v-tooltip activator="parent" location="bottom">
                    Whether the view follows the newest line. Scrolling up lets go of it; scrolling
                    back to the bottom takes hold again
                </v-tooltip>
            </v-btn>

            <v-btn
                :color="previousContainer ? 'warning' : undefined"
                :variant="previousContainer ? 'flat' : 'outlined'"
                size="small"
                @click="previousContainer = !previousContainer"
            >
                Previous container
                <v-tooltip activator="parent" location="bottom">
                    What the container that ran before this one said - what a crash left behind
                </v-tooltip>
            </v-btn>

            <v-menu>

                <template v-slot:activator="{ props }">
                    <v-btn
                        :loading="isPodsLoading"
                        v-bind="props"
                        variant="outlined" size="small"
                        class="pod-picker"
                    >
                        <template v-if="allPods">All pods</template>
                        <span v-else class="pod-picker-text">{{ activePod?.pod }} - {{ activePod?.container }}</span>
                    </v-btn>
                </template>
                <v-list>
                    <v-list-item
                        v-if="props.deploymentId"
                        :active="allPods"
                        @click="onAllPodsClicked"
                    >
                        <v-list-item-title>
                            <div class="d-flex">
                                <v-icon size="small" class="me-2 my-auto">fa fa-layer-group</v-icon>
                                <span class="my-auto">All pods, as one log</span>
                            </div>
                        </v-list-item-title>
                    </v-list-item>

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
            </div>
            </div>

        </v-toolbar>

        <v-progress-linear
            v-if="isLogsLoading || isPodsLoading"
            indeterminate/>

        <code
            ref="container"
            @scroll.passive="onLogScrolled">
            <span class="log-lines">
                <span
                    class="d-flex flex-nowrap log-row"
                    v-for="(line, i) in shownLogs" :key="i">
                    <span class="gutter">
                        <DateView
                            class="date-view"
                            text-format="DD/MM-YY HH:mm:ss"
                            :date="line.date"/>
                        <span v-if="line.pod" class="pod-name" :title="line.pod">{{ shortPodName(line.pod) }}</span>
                    </span>
                    <span class="code-line">{{ line.line }}</span>
                </span>
            </span>
        </code>

        <div class="overlay">
            <v-chip
                v-if="!follow && logs.length > 0"
                @click="onFollowClicked"
                style="cursor: pointer;"
                color="primary">
                <v-icon class="me-1">fas fa-angles-down</v-icon>
                <span>Jump to the newest line</span>
            </v-chip>

            <v-chip
                v-if="isWatching"
                color="success">
                <v-icon class="me-1">fas fa-circle-play</v-icon>
                <span>Watching...</span>
            </v-chip>

            <v-chip
                v-if="previousContainer && !isLogsLoading && logs.length === 0"
                color="warning">
                <v-icon class="me-1">fas fa-circle-info</v-icon>
                <span>No previous container - nothing has crashed and been replaced here</span>
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
.toolbar-wraps,
.toolbar-wraps :deep(.v-toolbar__content) {
    height: auto !important;
}

/* A pod's name is long; the button shows what fits, and the name is in the row above. */
.pod-picker {
    max-width: 100%;
}

.pod-picker-text {
    overflow: hidden;
    text-overflow: ellipsis;
}

/* The toolbar is two rows: what is being read, and how. */
.info {
    font-size: 12px;
    line-height: 1.25;
    white-space: nowrap;
}

.info strong {
    font-size: 13px;
}

.controls {
    height: auto;
}

/* On a phone the row wraps: search on a line of its own, the buttons under it. */
.search {
    flex: 1 1 200px;
    max-width: 280px;
}

.window {
    max-width: 180px;
}

/* Wide enough for `<replicaset hash>-<pod>`, which is what a rollout's two pods differ by - the
   hash is the half that says old or new, so neither end can be cut. 16 characters, as
   Kubernetes makes them. */
.pod-name {
    flex: 0 0 auto;
    width: 16ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    opacity: 0.6;
}

.overlay {
    position: fixed;
    z-index: 100;
    bottom: 4rem;
    right: 1rem;
    width: fit-content;

}

.date-view {
    /* `DD/MM-YY HH:mm:ss`, and not a character more. */
    width: 17ch;
    overflow: hidden;
    color: rgba(var(--v-theme-on-surface-muted), 0.55);
    flex-shrink: 0;
}

/* Every row as wide as the widest line, not the view: a sticky column only sticks within its
   row, and a shorter row scrolled out from under it. */
.log-lines {
    display: inline-flex;
    flex-direction: column;
    min-width: 100%;
}

/* The time and the pod stay put while the lines scroll sideways under them - opaque, or the
   lines showed through. */
.gutter {
    display: flex;
    flex-shrink: 0;
    gap: 1.5ch;
    padding: 0 1ch;
    margin-right: 1ch;
    position: sticky;
    left: 0;
    background: rgb(var(--v-theme-surface-muted));
    box-shadow: 4px 0 6px -4px rgba(0, 0, 0, 0.6);
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

code {
    /* Smaller than the app's text: a log is read in blocks, and more of it on screen at once is
       worth more than the size of any one line. */
    font-size: 11px;
    line-height: 1.45;
    flex: 1 1 auto;
    overflow: auto;
    /* So a flex child may be shorter than its content, which is what lets it scroll at all. */
    min-height: 0;
}

.code-line {

    white-space: pre;
    display: block;

    /*overflow-x: auto;*/
}
</style>
