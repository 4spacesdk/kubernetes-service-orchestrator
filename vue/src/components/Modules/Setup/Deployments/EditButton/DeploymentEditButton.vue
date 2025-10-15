<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {Deployment, DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DeploymentStatusTypes, WorkloadTypes} from "@/constants";

const props = defineProps<{
    deployment: Deployment
}>();

const spec = ref<DeploymentSpecification>();
const isLoading = ref(false);

const showUpdateVersion = ref(false);
const isUpdateVersionEnabled = ref(false);

const showUpdateImagePullPolicy = ref(false);
const isUpdateImagePullPolicyEnabled = ref(false);

const showUpdateEnvironment = ref(false);
const isUpdateEnvironmentEnabled = ref(false);

const showUpdateDatabaseService = ref(false);
const isUpdateDatabaseServiceEnabled = ref(false);

const showUpdateResourceManagement = ref(false);
const isUpdateResourceManagementEnabled = ref(false);

const showUpdateKNativeMinScaleSchedules = ref(false);
const isUpdateKNativeMinScaleSchedulesEnabled = ref(false);

const showUpdateUpdateManagement = ref(false);
const isUpdateUpdateManagementEnabled = ref(false);

const showUpdateEnvironmentVariables = ref(false);
const isUpdateEnvironmentVariablesEnabled = ref(false);

const showUpdateVolumes = ref(false);
const isUpdateVolumesEnabled = ref(false);

const showUpdateLabels = ref(false);
const isUpdateLabelsEnabled = ref(false);

const showUpdateCronJobs = ref(false);
const isUpdateCronJobsEnabled = ref(false);

onMounted(() => {
    isLoading.value = true;
    Api.deploymentSpecifications().getById(props.deployment.deployment_specification_id!)
        .find(specs => {
            spec.value = specs[0];
            render();
        });
});

function render() {
    showUpdateVersion.value = spec.value?.workload_type !== WorkloadTypes.CustomResource
    isUpdateVersionEnabled.value = true;

    showUpdateImagePullPolicy.value = spec.value?.workload_type !== WorkloadTypes.CustomResource
    isUpdateImagePullPolicyEnabled.value = true;

    showUpdateEnvironment.value = spec.value?.workload_type !== WorkloadTypes.CustomResource
    isUpdateEnvironmentEnabled.value = true;

    showUpdateDatabaseService.value = spec.value?.enable_database ?? false;
    isUpdateDatabaseServiceEnabled.value = props.deployment.status == DeploymentStatusTypes.Draft;

    showUpdateResourceManagement.value = spec.value?.workload_type !== WorkloadTypes.CustomResource
    isUpdateResourceManagementEnabled.value = true;

    showUpdateKNativeMinScaleSchedules.value = spec.value?.workload_type === WorkloadTypes.KNativeService
    isUpdateKNativeMinScaleSchedulesEnabled.value = true;

    showUpdateUpdateManagement.value = spec.value?.workload_type !== WorkloadTypes.CustomResource
    isUpdateUpdateManagementEnabled.value = true;

    showUpdateEnvironmentVariables.value = spec.value?.workload_type !== WorkloadTypes.CustomResource
    isUpdateEnvironmentVariablesEnabled.value = true;

    showUpdateVolumes.value = spec.value?.enable_volumes ?? false;
    isUpdateVolumesEnabled.value = true;

    showUpdateLabels.value = true;
    isUpdateLabelsEnabled.value = true;

    showUpdateCronJobs.value = spec.value?.enable_cronjob ?? false;
    isUpdateCronJobsEnabled.value = spec.value?.enable_cronjob ?? false;

    isLoading.value = false;
}

function onUpdateVersionClicked() {
    bus.emit('deploymentUpdateVersion', {
        deployment: props.deployment
    });
}

function onUpdateImagePullPolicyClicked() {
    bus.emit('deploymentUpdateImagePullPolicy', {
        deployment: props.deployment
    });
}

function onUpdateEnvironmentClicked() {
    bus.emit('deploymentUpdateEnvironment', {
        deployment: props.deployment
    });
}

function onUpdateDatabaseServiceClicked() {
    bus.emit('deploymentUpdateDatabaseService', {
        deployment: props.deployment
    });
}

function onUpdateResourceManagementClicked() {
    bus.emit('deploymentUpdateResourceManagement', {
        deployment: props.deployment
    });
}

function onUpdateKNativeMinScaleSchedulesClicked() {
    bus.emit('deploymentUpdateKNativeMinScaleSchedules', {
        deployment: props.deployment
    });
}

function onUpdateUpdateManagementClicked() {
    bus.emit('deploymentUpdateUpdateManagement', {
        deployment: props.deployment
    });
}

function onUpdateEnvironmentVariablesClicked() {
    bus.emit('deploymentUpdateEnvironmentVariables', {
        deployment: props.deployment
    });
}

function onUpdateVolumesClicked() {
    bus.emit('deploymentUpdateVolumes', {
        deployment: props.deployment
    });
}

function onUpdateLabelsClicked() {
    bus.emit('deploymentUpdateLabels', {
        deployment: props.deployment
    });
}

function onUpdateCronJobsClicked() {
    bus.emit('deploymentUpdateCronJobs', {
        deployment: props.deployment
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
                    v-if="showUpdateVersion"
                    :disabled="!isUpdateVersionEnabled"
                    dense
                    @click="onUpdateVersionClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-code-branch</v-icon>
                        <span class="ml-2">Version</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateImagePullPolicy"
                    :disabled="!isUpdateImagePullPolicyEnabled"
                    dense
                    @click="onUpdateImagePullPolicyClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-code-pull-request</v-icon>
                        <span class="ml-2">Image Pull Policy</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateEnvironment"
                    :disabled="!isUpdateEnvironmentEnabled"
                    dense
                    @click="onUpdateEnvironmentClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-file-code</v-icon>
                        <span class="ml-2">Environment</span>
                    </v-list-item-title>
                </v-list-item>
                <v-tooltip
                    v-if="showUpdateDatabaseService"
                    :disabled="isUpdateDatabaseServiceEnabled"
                    location="left">
                    <template v-slot:activator="{ props }">
                        <div
                            v-bind="props">
                            <v-list-item
                                :disabled="!isUpdateDatabaseServiceEnabled"
                                dense
                                @click="onUpdateDatabaseServiceClicked">
                                <v-list-item-title>
                                    <v-icon size="small" class="my-auto ml-2">fa fa-database</v-icon>
                                    <span class="ml-2">Database Service</span>
                                </v-list-item-title>
                            </v-list-item>
                            <v-divider/>
                        </div>
                    </template>
                    Only available during setup
                </v-tooltip>
                <v-list-item
                    v-if="showUpdateResourceManagement"
                    :disabled="!isUpdateResourceManagementEnabled"
                    dense
                    @click="onUpdateResourceManagementClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-plug-circle-bolt</v-icon>
                        <span class="ml-2">Resource Management</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateKNativeMinScaleSchedules"
                    :disabled="!isUpdateKNativeMinScaleSchedulesEnabled"
                    dense
                    @click="onUpdateKNativeMinScaleSchedulesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-clock</v-icon>
                        <span class="ml-2">KNative Min Scale Schedules</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateUpdateManagement"
                    :disabled="!isUpdateUpdateManagementEnabled"
                    dense
                    @click="onUpdateUpdateManagementClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-arrows-rotate</v-icon>
                        <span class="ml-2">Update Management</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateEnvironmentVariables"
                    :disabled="!isUpdateEnvironmentVariablesEnabled"
                    dense
                    @click="onUpdateEnvironmentVariablesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-key</v-icon>
                        <span class="ml-2">Environment Variables</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateVolumes"
                    :disabled="!isUpdateVolumesEnabled"
                    dense
                    @click="onUpdateVolumesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-hard-drive</v-icon>
                        <span class="ml-2">Volumes</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateLabels"
                    :disabled="!isUpdateLabelsEnabled"
                    dense
                    @click="onUpdateLabelsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-tags</v-icon>
                        <span class="ml-2">Labels</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateCronJobs"
                    :disabled="!isUpdateCronJobsEnabled"
                    dense
                    @click="onUpdateCronJobsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-clock</v-icon>
                        <span class="ml-2">Cron Jobs</span>
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
