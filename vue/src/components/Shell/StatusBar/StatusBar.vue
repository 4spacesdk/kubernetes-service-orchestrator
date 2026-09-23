<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref} from 'vue'
import {versions} from "@/versions";
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesNodeInfo} from "@/core/services/Deploy/Api";
import AuthService from "@/services/AuthService";
import moment from "moment";
import bus from "@/plugins/bus";

const version = ref(versions.version);

const isLoading = ref(false);
const status = ref<string>('');
const message = ref<string>('');
const nodes = ref<KubernetesNodeInfo[]>([]);
const nodesReady = ref<number>();
const nodesTotal = ref<number>();
const kubernetesVersion = ref<string>();
const healthCheckedAt = ref<string>();

/**
 * Minutes after which runtime health is old news. It is worked out every minute, so this is a
 * check that has stopped - a scheduler that is not running - while the cluster answers fine.
 * Said here, once, rather than on every row.
 */
const HealthStaleAfterMinutes = 3;
const healthIsStale = computed(() =>
    !!healthCheckedAt.value && moment().diff(moment(healthCheckedAt.value), "minutes") >= HealthStaleAfterMinutes
);

const statusInternal = ref<number>();

onMounted(() => {
    isLoading.value = true;
    getStatus();
    statusInternal.value = setInterval(() => {
        getStatus();
    }, 30000);
});

onUnmounted(() => {
    clearInterval(statusInternal.value);
});

function getStatus() {
    if (AuthService.isLoggedIn()) {
        Api.kubernetes().nodeInfoGet().find(value => {
            status.value = value[0].status ?? '';
            message.value = value[0].message ?? '';
            nodes.value = value[0].nodes ?? [];
            nodesReady.value = value[0].nodes_ready;
            nodesTotal.value = value[0].nodes_total;
            kubernetesVersion.value = value[0].kubernetes_version;
            healthCheckedAt.value = value[0].health_checked_at;
            isLoading.value = false;
        });
    }
}

/** The nodes, the counts and the scheduler - see `ClusterHealth` in the backend. Reload is in there. */
function onStatusClicked() {
    bus.emit('clusterHealth', {});
}

function onVersionBtnClicked() {
    if (version.value != '_VERSION_') {
        window.open('https://github.com/4spacesdk/kubernetes-service-orchestrator/releases/tag/' + version.value, '_blank');
    }
}

</script>

<template>
    <v-footer
        border
        height="30px"
        sticky
        app
    >
        <v-btn
            @click="onVersionBtnClicked"
            variant="text"
            size="small"
        >
            <small>{{ version }}</small>
        </v-btn>

        <div
            class="ml-auto d-flex"
        >
            <v-badge
                style="margin-bottom: 4px;"
                :color="isLoading ? 'warning' : (status != 'success' ? 'error' : (healthIsStale ? 'warning' : 'success'))"
                class="status-dot"
                @click="onStatusClicked"
            >
                <v-tooltip activator="parent" location="top">
                    <template v-if="isLoading">Loading...</template>
                    <template v-else-if="status != 'success'">{{ message }}</template>
                    <template v-else-if="healthIsStale">
                        Connected, but runtime health was last checked {{ moment(healthCheckedAt).fromNow() }} - is the scheduler running?
                    </template>
                    <template v-else>
                        <div>Connected<template v-if="kubernetesVersion"> - Kubernetes {{ kubernetesVersion }}</template></div>
                        <div v-if="nodesTotal !== undefined">{{ nodesReady }}/{{ nodesTotal }} nodes ready</div>
                        <div v-if="healthCheckedAt">Health checked {{ moment(healthCheckedAt).fromNow() }}</div>
                    </template>
                </v-tooltip>
            </v-badge>
        </div>
    </v-footer>
</template>

<style scoped>
.status-dot {
    cursor: pointer;
}
</style>
