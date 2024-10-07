<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import bus from "@/plugins/bus";
import {DeploymentSpecificationTypes} from "@/constants";

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const isVisible = ref(false);
const showUpdatePostCommands = ref(false);
const showUpdateEnvironmentVariables = ref(false);
const showUpdateServicePorts = ref(false);
const showUpdateIngresses = ref(false);
const showUpdateClusterRoleRules = ref(false);
const showUpdateServiceAnnotations = ref(false);
const showUpdateQuickCommands = ref(false);
const showUpdateInitContainers = ref(false);
const showUpdatePostUpdateActions = ref(false);
const isUpdatePostUpdateActionsEnabled = ref(false);

const showUpdateLabels = ref(false);
const isUpdateLabelsEnabled = ref(false);

onMounted(() => {
    render();

    watch(() => props.deploymentSpecification, _ => render());
});

function render() {
    isVisible.value = props.deploymentSpecification.type == DeploymentSpecificationTypes.Deployment;
    showUpdatePostCommands.value = props.deploymentSpecification.enable_database ?? false;
    showUpdateEnvironmentVariables.value = true;
    showUpdateServicePorts.value = true;
    showUpdateServiceAnnotations.value = true;
    showUpdateIngresses.value = props.deploymentSpecification.enable_ingress ?? false;
    showUpdateClusterRoleRules.value = props.deploymentSpecification.enable_rbac ?? false;
    showUpdateQuickCommands.value = true;
    showUpdateInitContainers.value = true;
    showUpdatePostUpdateActions.value = true;
    isUpdatePostUpdateActionsEnabled.value = props.deploymentSpecification.container_image?.version_control_enabled ?? false;

    showUpdateLabels.value = true;
    isUpdateLabelsEnabled.value = true;
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

function onUpdateClusterRoleRulesClicked() {
    bus.emit('deploymentSpecificationUpdateClusterRoleRules', {
        deploymentSpecification: props.deploymentSpecification
    });
}

function onUpdateServiceAnnotationsClicked() {
    bus.emit('deploymentSpecificationUpdateServiceAnnotations', {
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

</script>

<template>
    <div
        v-if="isVisible"
        class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">

            <v-list
                class="list-items">
                <v-list-item
                    v-if="showUpdatePostCommands"
                    dense
                    @click="onUpdatePostCommandsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-terminal</v-icon>
                        <span class="ml-2">Post Migration Commands</span>
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
                    v-if="showUpdateServiceAnnotations"
                    dense
                    @click="onUpdateServiceAnnotationsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-hashtag</v-icon>
                        <span class="ml-2">Service Annotations</span>
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
                    v-if="showUpdateIngresses"
                    dense
                    @click="onUpdateIngressesClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-link</v-icon>
                        <span class="ml-2">Ingresses</span>
                    </v-list-item-title>
                </v-list-item>
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
                    v-if="showUpdateQuickCommands"
                    dense
                    @click="onUpdateQuickCommandsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-terminal</v-icon>
                        <span class="ml-2">Quick Commands</span>
                    </v-list-item-title>
                </v-list-item>
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
                    v-if="showUpdateLabels"
                    :disabled="!isUpdateLabelsEnabled"
                    dense
                    @click="onUpdateLabelsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-tags</v-icon>
                        <span class="ml-2">Labels</span>
                    </v-list-item-title>
                </v-list-item>
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
                        </div>
                    </template>
                    Setup container image version control to enable post update actions
                </v-tooltip>
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
