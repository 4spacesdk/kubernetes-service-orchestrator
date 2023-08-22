<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DeploymentStep} from "@/core/services/Deploy/Api";
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

const props = defineProps<{
    step: DeploymentStep,
    deployment: Deployment,
}>();

const text = ref('');
const icon = ref('fa-circle-dashed');
const color = ref('grey');
const isLoading = ref(false);
const isHovering = ref(false);

onMounted(() => {
    reload();

    bus.on('deployment_DeploymentStep_Status_Change', onStatusChangeEvent);
});

onUnmounted(() => {
    bus.off('deployment_DeploymentStep_Status_Change', onStatusChangeEvent);
});

function onStatusChangeEvent(input: { deployment: Deployment, step: DeploymentStep }) {
    if (props.deployment.id === input.deployment.id
        && props.step.identifier === input.step.identifier) {
        reload();
    }
}

function reload() {
    isLoading.value = true;
    const api = Api.deploymentSteps().getStatusGetByIdentifier(props.step.identifier!)
        .deploymentId(props.deployment.id!);
    api.setErrorHandler((response: any) => {
        text.value = response.error ?? 'failed to load';
        icon.value = 'fa-circle';
        color.value = 'grey';
        isLoading.value = false;
        return false;
    });
    api.find(value => {
        text.value = value[0].value ?? '';
        switch (value[0].value) {
            case 'not-found':
            case 'not-performed':
            case 'running':
                icon.value = 'fa-circle-exclamation';
                color.value = 'warning';
                break;

            case 'failed':
                icon.value = 'fa-circle-xmark';
                color.value = 'red';
                break;

            case 'found':
            case 'success':
            case 'completed':
                icon.value = 'fa-circle-check';
                color.value = 'green';
                break;

            default:
                icon.value = 'fa-circle';
                color.value = 'grey';
                break;
        }
        isLoading.value = false;
    });
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
            <v-icon :color="color" class="me-1">fa {{ icon }}</v-icon>
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
