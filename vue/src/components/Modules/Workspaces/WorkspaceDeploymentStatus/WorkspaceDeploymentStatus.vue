<script setup lang="ts">
import { computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { Deployment, Workspace } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import { DeploymentStatusTypes } from "@/constants";
import { PushSubscription } from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import { ChangeEvent } from "@/services/Push/ChangeEvent";

const props = defineProps<{
    workspace: Workspace;
}>();

const deployments = ref<Deployment[]>([]);
const text = ref("unknown");
const icon = ref("fa-circle-info");
const color = ref("grey");
const isLoading = ref(false);
const isHovering = ref(false);
const deploymentsInDraftStatus = ref<Deployment[]>([]);
const deploymentsOutOfSync = ref<Deployment[]>([]);
const deploymentsSynced = ref<Deployment[]>([]);
const deploymentsInInactiveStatus = ref<Deployment[]>([]);
const pushSubscription = ref<PushSubscription>();

onMounted(() => {
    deployments.value = props.workspace.deployments ?? [];
    setup();
});

watch(
    () => props.workspace,
    (newWorkspace) => {
        deployments.value = props.workspace.deployments ?? [];
        setup();
    }
);

onUnmounted(() => {
    pushSubscription.value?.unsubscribe();
});

function setup() {
    pushSubscription.value?.unsubscribe();
    pushSubscription.value = PushService.subscribe(Events.Workspace_Changed_Status(props.workspace.id!), (data) => {
        const changeEvent = new ChangeEvent<Workspace>(data.previous, new Workspace(data.next));
        deployments.value = changeEvent.next.deployments ?? [];
        render();
    });

    render();
}

function reload() {
    isLoading.value = true;
    Api.workspaces()
        .getStatusGetById(props.workspace.id!)
        .find((workspace) => {
            deployments.value = workspace[0]?.deployments ?? [];
            render();
            isLoading.value = false;
        });
}

function render() {
    deploymentsInDraftStatus.value = deployments.value.filter((deployment) => deployment.status == DeploymentStatusTypes.Draft);
    deploymentsOutOfSync.value = deployments.value.filter((deployment) => deployment.status == DeploymentStatusTypes.OutOfSync);
    deploymentsSynced.value = deployments.value.filter((deployment) => deployment.status == DeploymentStatusTypes.Synced);
    deploymentsInInactiveStatus.value = deployments.value.filter((deployment) => deployment.status == DeploymentStatusTypes.Inactive);

    // A pause is a decision somebody made, and it outranks whatever the deployments add up
    // to: the status is recomputed from them, so it drifts on its own.
    if (props.workspace.is_paused) {
        icon.value = "fa fa-pause";
        color.value = "grey";
        text.value = "Paused";
    } else if (deploymentsOutOfSync.value.length) {
        icon.value = "fa fa-circle-arrow-up";
        color.value = "warning";
        text.value = "Out of sync";
    } else if (deploymentsInDraftStatus.value.length) {
        icon.value = "fa fa-check";
        color.value = "grey";
        text.value = "Draft";
    } else if (deploymentsInInactiveStatus.value.length) {
        icon.value = "fa fa-check";
        color.value = "grey";
        text.value = "Inactive";
    } else if (deploymentsSynced.value.length) {
        icon.value = "fa fa-check";
        color.value = "success";
        text.value = "Synced";
    } else {
        icon.value = "fa fa-circle-info";
        color.value = "grey";
        text.value = "Unknown";
    }
}

// <editor-fold desc="View function bindings">

function onRefreshBtnClicked() {
    reload();
}

// </editor-fold>
</script>

<template>
    <v-menu left min-width="250" offset-y>
        <template v-slot:activator="{ props }">
            <div class="d-flex justify-start align-center" @mouseover="isHovering = true" @mouseleave="isHovering = false">
                <v-btn @click="onRefreshBtnClicked" v-if="!isHovering" :loading="isLoading" variant="plain" color="grey" size="small" icon>
                    <v-icon :color="color">fal {{ icon }}</v-icon>
                    <v-tooltip activator="parent" location="bottom" v-if="text">{{ text }}</v-tooltip>
                </v-btn>
                <v-btn v-if="isHovering" :loading="isLoading" variant="plain" color="grey" size="small" icon @click="onRefreshBtnClicked">
                    <v-icon>fa fa-arrows-rotate</v-icon>
                    <v-tooltip activator="parent" location="bottom">Refresh</v-tooltip>
                </v-btn>

                <span class="status">{{ text }}</span>
            </div>
        </template>

        <v-card class="pb-0">
            <v-card-title>Deployments</v-card-title>
            <v-card-text class="mb-0 border pb-0 min-height-unset">
                <v-row>
                    <v-col cols="12">
                        <div class="d-flex align-center w-100 justify-space-between">
                            <v-icon class="me-1" color="success">fa fa-check</v-icon>
                            <div class="d-flex align-center"><span>Synced</span></div>
                            <v-chip size="small" class="ms-auto" color="success">{{ deploymentsSynced.length }}</v-chip>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <div class="d-flex align-center w-100">
                            <v-icon class="me-1" color="warning">fa fa-circle-arrow-up</v-icon>
                            <div class="d-flex align-center"><span>Out of sync</span></div>
                            <v-chip size="small" class="ms-auto" color="warning">{{ deploymentsOutOfSync.length }}</v-chip>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <div class="d-flex align-center w-100">
                            <v-icon class="me-1" color="grey">fa fa-check</v-icon>
                            <div class="d-flex align-center"><span>Drafts</span></div>
                            <v-chip size="small" class="ms-auto" color="grey">{{ deploymentsInDraftStatus.length }}</v-chip>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <div class="d-flex align-center w-100">
                            <v-icon class="me-1" color="grey">fa fa-check</v-icon>
                            <div class="d-flex align-center"><span>Inactive</span></div>
                            <v-chip size="small" class="ms-auto" color="grey">{{ deploymentsInInactiveStatus.length }}</v-chip>
                        </div>
                    </v-col>

                </v-row>
            </v-card-text>
        </v-card>
    </v-menu>
</template>

<style scoped>
/* "Out of sync" broke over three lines and made the row three lines tall. */
.status {
    white-space: nowrap;
}
</style>
