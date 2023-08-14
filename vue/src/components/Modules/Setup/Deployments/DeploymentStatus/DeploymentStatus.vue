<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {DeploymentStatusTypes} from "@/constants";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";
import {ChangeEvent} from "@/services/Wamp/ChangeEvent";

const props = defineProps<{
    deployment: Deployment,
}>();

const text = ref('unknown');
const icon = ref('fa-circle-info');
const color = ref('grey');
const isLoading = ref(false);
const isHovering = ref(false);
const wampSubscription = ref<WampSubscription>();

onMounted(() => {
    render(props.deployment.status!);

    wampSubscription.value = WampService.subscribe(
        Events.Deployment_Changed_Status(props.deployment.id!),
        data => {
            const changeEvent = new ChangeEvent<Deployment>(data.previous, new Deployment(data.next));
            render(changeEvent.next.status!);
        }
    );
});

onUnmounted(() => {
    wampSubscription.value?.unsubscribe();
});

function reload() {
    isLoading.value = true;
    Api.deployments().getStatusGetById(props.deployment.id!)
        .find(deployments => {
            render(deployments[0].status!);
            isLoading.value = false;
        });
}

function render(status: string) {
    switch (status) {
        case DeploymentStatusTypes.Draft:
            icon.value = 'fa fa-check';
            color.value = 'grey';
            text.value =  'Draft';
            break;
        case DeploymentStatusTypes.Deploying:
            icon.value = 'fa fa-box';
            color.value = 'orange';
            text.value =  'Deploying';
            break;
        case DeploymentStatusTypes.Active:
            icon.value = 'fa fa-check';
            color.value = 'green';
            text.value =  'Active';
            break;
        case DeploymentStatusTypes.Error:
            icon.value = 'fa fa-circle-xmark';
            color.value = 'red';
            text.value =  'Error';
            break;
        default:
            icon.value = 'fa fa-circle-info';
            color.value = 'grey';
            text.value =  'Unknown';
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
    <div class="d-flex justify-start align-center"
         @mouseover="isHovering = true"
         @mouseleave="isHovering = false"
    >

        <v-btn
            @click="onRefreshBtnClicked"
            v-if="!isHovering"
            :loading="isLoading"
            variant="plain" color="grey" size="small" icon
        >
            <v-icon :color="color" class="me-1">fal {{ icon }}</v-icon>
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

        <span>{{ text }}</span>

    </div>
</template>

<style scoped>

</style>
