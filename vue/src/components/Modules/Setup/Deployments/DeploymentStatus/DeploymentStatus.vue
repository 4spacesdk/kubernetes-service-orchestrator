<script setup lang="ts">
import { computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { Deployment } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import { DeploymentStatusTypes } from "@/constants";
import { PushSubscription } from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import { ChangeEvent } from "@/services/Push/ChangeEvent";

const props = defineProps<{
    deployment: Deployment;
}>();

const text = ref("unknown");
const icon = ref("fa-circle-info");
const color = ref("grey");
const isLoading = ref(false);
const isHovering = ref(false);
const pushSubscription = ref<PushSubscription>();

onMounted(() => {
    render(props.deployment.status!);

    pushSubscription.value = PushService.subscribe(Events.Deployment_Changed_Status(props.deployment.id!), (data) => {
        const changeEvent = new ChangeEvent<Deployment>(data.previous, new Deployment(data.next));
        render(changeEvent.next.status!);
    });
});

onUnmounted(() => {
    pushSubscription.value?.unsubscribe();
});

function reload() {
    isLoading.value = true;
    Api.deployments()
        .getStatusGetById(props.deployment.id!)
        .find((deployments) => {
            render(deployments[0].status!);
            isLoading.value = false;
        });
}

function render(status: string) {
    switch (status) {
        case DeploymentStatusTypes.Draft:
            icon.value = "fa fa-check";
            color.value = "grey";
            text.value = "Draft";
            break;
        case DeploymentStatusTypes.Inactive:
            icon.value = "fa fa-check";
            color.value = "grey";
            text.value = "Inactive";
            break;
        case DeploymentStatusTypes.OutOfSync:
            icon.value = "fa fa-circle-arrow-up";
            color.value = "orange";
            text.value = "Out of sync";
            break;
        case DeploymentStatusTypes.Synced:
            icon.value = "fa fa-check";
            color.value = "green";
            text.value = "Synced";
            break;
        default:
            icon.value = "fa fa-circle-info";
            color.value = "grey";
            text.value = "Unknown";
            break;
    }
}

// <editor-fold desc="View function bindings">

function onRefreshBtnClicked() {
    reload();
}

// </editor-fold>
</script>

<template>
    <div class="d-flex justify-start align-center" @mouseover="isHovering = true" @mouseleave="isHovering = false">
        <v-btn @click="onRefreshBtnClicked" v-if="!isHovering" :loading="isLoading" variant="plain" color="grey" size="small" icon>
            <v-icon :color="color" class="me-1">fal {{ icon }}</v-icon>
            <v-tooltip activator="parent" location="bottom" v-if="text">{{ text }}</v-tooltip>
        </v-btn>

        <v-btn v-if="isHovering" :loading="isLoading" variant="plain" color="grey" size="small" icon @click="onRefreshBtnClicked">
            <v-icon>fa fa-arrows-rotate</v-icon>
            <v-tooltip activator="parent" location="bottom">Refresh</v-tooltip>
        </v-btn>

        <span class="status">{{ text }}</span>
    </div>
</template>

<style scoped>
/* "Out of sync" broke over three lines and made every row three lines tall. */
.status {
    white-space: nowrap;
}
</style>
