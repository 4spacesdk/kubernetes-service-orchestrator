<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesPod} from "@/core/services/Deploy/Api";
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

onMounted(() => {
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
            render();
        });
});

function render() {
    isLoading.value = false;
}

function onShowLogsBtnClicked(item: PodOption) {
    bus.emit('deploymentLogs', {
        deployment: props.deployment,
        preselectedPodName: item.pod.name,
    });
}

function onOpenTerminalBtnClicked(item: PodOption) {
    bus.emit('podTerminal', {
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
            <v-list
                v-if="!isLoading"
                class="list-items">
                <v-list-item
                    v-for="pod in pods"
                >
                    <v-list-item-title>
                        <div class="d-flex">
                            <div
                                class="my-auto"
                                style="width: 100px;">
                                <v-chip
                                    :color="pod.statusColor"
                                    size="small">{{ pod.pod.status }}
                                </v-chip>
                            </div>

                            <span class="my-auto">{{ pod.pod.name }}</span>

                            <DateView
                                class="ml-auto pl-4 my-auto"
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
.list-wrapper {
    min-width: 120px;
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
