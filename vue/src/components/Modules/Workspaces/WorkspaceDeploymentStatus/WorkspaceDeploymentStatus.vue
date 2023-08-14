<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {DeploymentStatusTypes} from "@/constants";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";
import {ChangeEvent} from "@/services/Wamp/ChangeEvent";

const props = defineProps<{
    workspace: Workspace,
}>();

const deployments = ref<Deployment[]>([]);
const text = ref('unknown');
const icon = ref('fa-circle-info');
const color = ref('grey');
const isLoading = ref(false);
const isHovering = ref(false);
const deploymentsInDraftStatus = ref<Deployment[]>([]);
const deploymentsInDeploymentStatus = ref<Deployment[]>([]);
const deploymentsInActiveStatus = ref<Deployment[]>([]);
const deploymentsInErrorStatus = ref<Deployment[]>([]);
const wampSubscription = ref<WampSubscription>();

onMounted(() => {
    deployments.value = props.workspace.deployments ?? [];
    setup();
});

watch(() => props.workspace, newWorkspace => {
    deployments.value = props.workspace.deployments ?? [];
    setup();
});

onUnmounted(() => {
    wampSubscription.value?.unsubscribe();
});

function setup() {
    wampSubscription.value?.unsubscribe();
    wampSubscription.value = WampService.subscribe(
        Events.Workspace_Changed_Status(props.workspace.id!),
        data => {
            const changeEvent = new ChangeEvent<Workspace>(data.previous, new Workspace(data.next));
            deployments.value = changeEvent.next.deployments ?? [];
            render();
        }
    );

    render();
}

function reload() {
    isLoading.value = true;
    Api.workspaces().getStatusGetById(props.workspace.id!)
        .find(workspace => {
            deployments.value = workspace[0]?.deployments ?? [];
            render();
            isLoading.value = false;
        });
}

function render() {
    deploymentsInDraftStatus.value = deployments.value.filter(deployment => deployment.status == DeploymentStatusTypes.Draft);
    deploymentsInDeploymentStatus.value = deployments.value.filter(deployment => deployment.status == DeploymentStatusTypes.Deploying);
    deploymentsInActiveStatus.value = deployments.value.filter(deployment => deployment.status == DeploymentStatusTypes.Active);
    deploymentsInErrorStatus.value = deployments.value.filter(deployment => deployment.status == DeploymentStatusTypes.Error);

    if (deploymentsInErrorStatus.value.length) {
        icon.value = 'fa fa-circle-xmark';
        color.value = 'red';
        text.value = 'Error';
    } else if (deploymentsInDeploymentStatus.value.length) {
        icon.value = 'fa fa-box';
        color.value = 'orange';
        text.value = 'Deploying';
    } else if (deploymentsInDraftStatus.value.length) {
        icon.value = 'fa fa-check';
        color.value = 'grey';
        text.value = 'Draft';
    } else if (deploymentsInActiveStatus.value.length) {
        icon.value = 'fa fa-check';
        color.value = 'green';
        text.value = 'Active';
    } else {
        icon.value = 'fa fa-circle-info';
        color.value = 'grey';
        text.value = 'Unknown';
    }
}

// <editor-fold desc="View function bindings">

function onRefreshBtnClicked() {
    reload();
}

// </editor-fold>

</script>

<template>
    <v-menu
        left
        min-width="250"
        offset-y>
        <template v-slot:activator="{props}">
            <div
                class="d-flex justify-start align-center"
                @mouseover="isHovering = true"
                @mouseleave="isHovering = false"
            >
                <v-btn
                    @click="onRefreshBtnClicked"
                    v-if="!isHovering"
                    :loading="isLoading"
                    variant="plain" color="grey" size="small" icon
                >
                    <v-icon :color="color">fal {{ icon }}</v-icon>
                    <v-tooltip activator="parent" location="bottom" v-if="text">{{ text }}</v-tooltip>
                </v-btn>
                <v-btn
                    v-if="isHovering"
                    :loading="isLoading"
                    variant="plain" color="grey" size="small" icon
                    @click="onRefreshBtnClicked">
                    <v-icon>fa fa-arrows-rotate</v-icon>
                    <v-tooltip activator="parent" location="bottom">Refresh</v-tooltip>
                </v-btn>

                <v-btn
                    v-bind="props"
                    variant="plain" color="grey" size="small" icon>
                    <v-icon>fa fa-circle-info</v-icon>
                    <v-tooltip activator="parent" location="bottom">Show deployments</v-tooltip>
                </v-btn>

                <span>{{ text }}</span>
            </div>
        </template>

        <v-card class="pb-0">
            <v-card-title>Deployments</v-card-title>
            <v-card-text class="mb-0 border pb-0 min-height-unset">
                <v-row>
                    <v-col cols="12">
                        <div class="d-flex align-center w-100 justify-space-between">
                            <v-icon class="me-1" color="green">fa fa-check</v-icon>
                            <div class="d-flex align-center"><span>Active</span> </div>
                            <v-chip size="small" class="ms-auto" color="green">{{ deploymentsInActiveStatus.length }}</v-chip>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <div class="d-flex align-center w-100">
                            <v-icon class="me-1" color="orange">fa fa-box</v-icon>
                            <div class="d-flex align-center"><span>Deploying</span> </div>
                            <v-chip size="small" class="ms-auto" color="orange">{{ deploymentsInDeploymentStatus.length }}</v-chip>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <div class="d-flex align-center w-100">
                            <v-icon class="me-1" color="grey">fa fa-check</v-icon>
                            <div class="d-flex align-center"><span>Drafts</span> </div>
                            <v-chip size="small" class="ms-auto" color="grey">{{ deploymentsInDraftStatus.length }}</v-chip>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <div class="d-flex align-center w-100">
                            <v-icon class="me-1" color="red">fa fa-circle-xmark</v-icon>
                            <div class="d-flex align-center"><span>Error</span> </div>
                            <v-chip size="small" class="ms-auto" color="red">{{ deploymentsInErrorStatus.length }}</v-chip>
                        </div>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>
    </v-menu>
</template>

<style scoped>

</style>
