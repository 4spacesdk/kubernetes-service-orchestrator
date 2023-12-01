<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import bus from "@/plugins/bus";

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const showUpdatePostCommands = ref(false);
const showUpdateEnvironmentVariables = ref(false);
const showUpdateServicePorts = ref(false);
const showUpdateIngresses = ref(false);
const showUpdateClusterRoleRules = ref(false);
const showUpdateServiceAnnotations = ref(false);

onMounted(() => {
    render();

    watch(() => props.deploymentSpecification, _ => render());
});

function render() {
    showUpdatePostCommands.value = true;
    showUpdateEnvironmentVariables.value = true;
    showUpdateServicePorts.value = true;
    showUpdateServiceAnnotations.value = true;
    showUpdateIngresses.value = props.deploymentSpecification.enable_ingress ?? false;
    showUpdateClusterRoleRules.value = props.deploymentSpecification.enable_rbac ?? false;
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

</script>

<template>
    <div
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
                        <span class="ml-2">Post Commands</span>
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
