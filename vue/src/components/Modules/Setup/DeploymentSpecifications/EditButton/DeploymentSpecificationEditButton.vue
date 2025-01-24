<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import bus from "@/plugins/bus";
import {NetworkTypes, WorkloadTypes} from "@/constants";

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const isVisible = ref(false);
const showUpdatePostCommands = ref(false);
const showUpdateEnvironmentVariables = ref(false);
const showUpdateServicePorts = ref(false);
const showNetwork = ref(false);
const showUpdateIngresses = ref(false);
const showUpdateHttpProxyRoutes = ref(false);
const showUpdateClusterRoleRules = ref(false);
const showUpdateRoleRules = ref(false);
const showUpdateServiceAnnotations = ref(false);
const showUpdateDeploymentAnnotations = ref(false);
const showUpdateQuickCommands = ref(false);
const showUpdateInitContainers = ref(false);
const showUpdatePostUpdateActions = ref(false);
const isUpdatePostUpdateActionsEnabled = ref(false);
const showUpdateCronJobs = ref(false);
const isUpdateCronJobsEnabled = ref(false);

const showUpdateLabels = ref(false);
const isUpdateLabelsEnabled = ref(false);

onMounted(() => {
    render();

    watch(() => props.deploymentSpecification, _ => render());
});

function render() {
    // Workload
    showUpdateInitContainers.value = props.deploymentSpecification.workload_type != WorkloadTypes.CustomResource;
    showUpdateEnvironmentVariables.value = props.deploymentSpecification.workload_type != WorkloadTypes.CustomResource;
    showUpdateDeploymentAnnotations.value = props.deploymentSpecification.workload_type != WorkloadTypes.CustomResource;

    // Network
    showNetwork.value = (props.deploymentSpecification.enable_external_access ?? false)
        || (props.deploymentSpecification.enable_internal_access ?? false)
    showUpdateIngresses.value = (props.deploymentSpecification.enable_external_access ?? false)
        && props.deploymentSpecification.network_type == NetworkTypes.NginxIngress;
    showUpdateHttpProxyRoutes.value = (props.deploymentSpecification.enable_external_access ?? false)
        && props.deploymentSpecification.network_type == NetworkTypes.Contour;
    showUpdateServicePorts.value = (props.deploymentSpecification.enable_internal_access ?? false)
        && props.deploymentSpecification.workload_type !== WorkloadTypes.KNativeService;
    showUpdateServiceAnnotations.value = (props.deploymentSpecification.enable_internal_access ?? false)
        && props.deploymentSpecification.workload_type == WorkloadTypes.Deployment;

    // Cronjob
    showUpdateCronJobs.value = true;
    isUpdateCronJobsEnabled.value = props.deploymentSpecification.enable_cronjob ?? false;

    // Migration
    showUpdatePostCommands.value = props.deploymentSpecification.enable_database ?? false;

    // Update
    showUpdatePostUpdateActions.value = props.deploymentSpecification.workload_type != WorkloadTypes.CustomResource;
    isUpdatePostUpdateActionsEnabled.value = props.deploymentSpecification.container_image?.version_control_enabled ?? false;

    // RBAC
    showUpdateClusterRoleRules.value = props.deploymentSpecification.enable_rbac ?? false;
    showUpdateRoleRules.value = props.deploymentSpecification.enable_rbac ?? false;

    // Other
    showUpdateQuickCommands.value = props.deploymentSpecification.workload_type != WorkloadTypes.CustomResource;
    showUpdateLabels.value = true;
    isUpdateLabelsEnabled.value = true;

    isVisible.value = true;
}

function onUpdatePostCommandsClicked() {
    bus.emit('deploymentSpecificationUpdatePostCommands', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateEnvironmentVariablesClicked() {
    bus.emit('deploymentSpecificationUpdateEnvironmentVariables', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateServicePortsClicked() {
    bus.emit('deploymentSpecificationUpdateServicePorts', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateIngressesClicked() {
    bus.emit('deploymentSpecificationUpdateIngresses', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateHttpProxyRoutesClicked() {
    bus.emit('deploymentSpecificationUpdateHttpProxyRoutes', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateClusterRoleRulesClicked() {
    bus.emit('deploymentSpecificationUpdateClusterRoleRules', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateRoleRulesClicked() {
    bus.emit('deploymentSpecificationUpdateRoleRules', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateServiceAnnotationsClicked() {
    bus.emit('deploymentSpecificationUpdateServiceAnnotations', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateDeploymentAnnotationsClicked() {
    bus.emit('deploymentSpecificationUpdateDeploymentAnnotations', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateQuickCommandsClicked() {
    bus.emit('deploymentSpecificationUpdateQuickCommands', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateInitContainersClicked() {
    bus.emit('deploymentSpecificationUpdateInitContainers', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdatePostUpdateActionsClicked() {
    bus.emit('deploymentSpecificationUpdatePostUpdateActions', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateLabelsClicked() {
    bus.emit('deploymentSpecificationUpdateLabels', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateCronJobsClicked() {
    bus.emit('deploymentSpecificationUpdateCronJobs', {
        deploymentSpecification: props.deploymentSpecification
    });
}

</script>

<template>
    <div
        v-if="isVisible"
        class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">

            <v-list
                class="list-items">

                <v-list-subheader
                    v-if="props.deploymentSpecification.workload_type != WorkloadTypes.CustomResource"
                >Workload - {{ props.deploymentSpecification.workload_type }}</v-list-subheader>
                <v-list-item
                    v-if="showUpdateInitContainers"
                    dense
                    @click="onUpdateInitContainersClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-box</v-icon>
                        <span class="ml-2">Init Containers</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateEnvironmentVariables"
                    dense
                    @click="onUpdateEnvironmentVariablesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-key</v-icon>
                        <span class="ml-2">Environment Variables</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateDeploymentAnnotations"
                    dense
                    @click="onUpdateDeploymentAnnotationsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-tags</v-icon>
                        <span class="ml-2">Deployment Annotations</span>
                    </v-list-item-title>
                </v-list-item>


                <v-list-subheader v-if="showNetwork">Network - {{ props.deploymentSpecification.network_type }}</v-list-subheader>
                <v-list-item
                    v-if="showUpdateIngresses"
                    dense
                    @click="onUpdateIngressesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-link</v-icon>
                        <span class="ml-2">Ingresses</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateHttpProxyRoutes"
                    dense
                    @click="onUpdateHttpProxyRoutesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-link</v-icon>
                        <span class="ml-2">Http Proxy Routes</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateServicePorts"
                    dense
                    @click="onUpdateServicePortsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-hashtag</v-icon>
                        <span class="ml-2">Service Ports</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateServiceAnnotations"
                    dense
                    @click="onUpdateServiceAnnotationsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-tags</v-icon>
                        <span class="ml-2">Service Annotations</span>
                    </v-list-item-title>
                </v-list-item>


                <v-list-subheader v-if="showUpdateCronJobs">Cronjob</v-list-subheader>
                <v-tooltip
                    v-if="showUpdateCronJobs"
                    :disabled="isUpdateCronJobsEnabled"
                    location="left">
                    <template v-slot:activator="{ props }">
                        <div
                            v-bind="props">
                            <v-list-item
                                dense
                                @click="onUpdateCronJobsClicked"
                                :disabled="!isUpdateCronJobsEnabled"
                            >
                                <v-list-item-title>
                                    <v-icon size="small" class="my-auto ml-2">fa fa-clock</v-icon>
                                    <span class="ml-2">Cron Jobs</span>
                                </v-list-item-title>
                            </v-list-item>
                            <v-divider/>
                        </div>
                    </template>
                    Enable Cron Jobs in deployment specification edit dialog
                </v-tooltip>


                <v-list-subheader v-if="showUpdatePostCommands">Migration</v-list-subheader>
                <v-list-item
                    v-if="showUpdatePostCommands"
                    dense
                    @click="onUpdatePostCommandsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-terminal</v-icon>
                        <span class="ml-2">Post Migration Commands</span>
                    </v-list-item-title>
                </v-list-item>


                <v-list-subheader v-if="showUpdateClusterRoleRules || showUpdateRoleRules">RBAC</v-list-subheader>
                <v-list-item
                    v-if="showUpdateClusterRoleRules"
                    dense
                    @click="onUpdateClusterRoleRulesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-shield</v-icon>
                        <span class="ml-2">Cluster Role Rules</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showUpdateRoleRules"
                    dense
                    @click="onUpdateRoleRulesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-shield</v-icon>
                        <span class="ml-2">Role Rules</span>
                    </v-list-item-title>
                </v-list-item>


                <v-list-subheader v-if="showUpdatePostUpdateActions">Update</v-list-subheader>
                <v-tooltip
                    v-if="showUpdatePostUpdateActions"
                    :disabled="isUpdatePostUpdateActionsEnabled"
                    location="left">
                    <template v-slot:activator="{ props }">
                        <div
                            v-bind="props">
                            <v-list-item
                                dense
                                @click="onUpdatePostUpdateActionsClicked"
                                :disabled="!isUpdatePostUpdateActionsEnabled"
                            >
                                <v-list-item-title>
                                    <v-icon size="small" class="my-auto ml-2">fa fa-terminal</v-icon>
                                    <span class="ml-2">Post Update Actions</span>
                                </v-list-item-title>
                            </v-list-item>
                            <v-divider/>
                        </div>
                    </template>
                    Setup container image version control to enable post update actions
                </v-tooltip>


                <v-list-subheader>Other</v-list-subheader>
                <v-list-item
                    v-if="showUpdateQuickCommands"
                    dense
                    @click="onUpdateQuickCommandsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-terminal</v-icon>
                        <span class="ml-2">Quick Commands</span>
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
