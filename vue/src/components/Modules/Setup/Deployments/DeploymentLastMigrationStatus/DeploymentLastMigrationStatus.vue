<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, MigrationJob} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {DeploymentStatusTypes, MigrationJobStatusTypes} from "@/constants";
import bus from "@/plugins/bus";

const props = defineProps<{
    deployment: Deployment,
}>();

const text = ref('unknown');
const icon = ref('fa-circle-info');
const color = ref('grey');
const isLoading = ref(false);
const isHovering = ref(false);
const lastMigrationJob = ref<MigrationJob>();

onMounted(() => {
    lastMigrationJob.value = props.deployment.last_migration_job;
    render();
});

onUnmounted(() => {
});

function reload() {
    isLoading.value = true;
    Api.deployments().get()
        .include('last_migration_job')
        .where('id', props.deployment.id!)
        .find(deployments => {
            lastMigrationJob.value = deployments[0].last_migration_job;
            render();
            isLoading.value = false;
        });
}

function render() {
    const status = lastMigrationJob.value?.status;
    switch (status) {
        case MigrationJobStatusTypes.Deploying:
            icon.value = 'fa fa-box';
            color.value = 'orange';
            text.value =  'Deploying';
            break;
        case MigrationJobStatusTypes.Started:
            icon.value = 'fa fa-box';
            color.value = 'orange';
            text.value =  'Started';
            break;
        case MigrationJobStatusTypes.Completed:
            icon.value = 'fa fa-check';
            color.value = 'green';
            text.value =  'Completed';
            break;
        case MigrationJobStatusTypes.FailedLogVerification:
            icon.value = 'fa fa-circle-xmark';
            color.value = 'red';
            text.value =  'Failed (Log verification)';
            break;
        case MigrationJobStatusTypes.Failed_PostCommands:
            icon.value = 'fa fa-circle-xmark';
            color.value = 'red';
            text.value =  'Failed (Post commands)';
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

function onShowLogBtnClicked() {

    const code = lastMigrationJob.value?.log?.trim().split('\n').map(k => {
        if(k.length)
            return `<span>${k}</span>`;
    }).join('');

    bus.emit('info', {
        title: 'Migration Job: Log',
        body: `<code class="line-numbers">${code}</code>`
    })
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

        <span
            v-if="!isHovering">{{ text }}</span>

        <v-btn
            v-if="isHovering"
            :loading="isLoading"
            variant="plain" color="grey" size="small"
            @click="onShowLogBtnClicked">
            <v-icon>fa fa-rectangle-list</v-icon>
            <v-tooltip activator="parent" location="bottom">Log</v-tooltip>
        </v-btn>

    </div>
</template>

<style scoped>

</style>
