<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {DeploymentSpecification, System} from "@/core/services/Deploy/models";
import {HealthCheckTypes, HostingProviders, NetworkTypes} from "@/constants";

export interface DeploymentSpecificationUpdateServicePortDialog_Input {
    deploymentSpecification: DeploymentSpecification;
    servicePort: {
        protocol?: string;
        name?: string;
        port?: number;
        targetPort?: number;
        healthCheckType?: string;
        healthCheckPath?: string;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateServicePortDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const formIsValid = ref(false);
const showProtocol = ref(false);
const showTargetPort = ref(false);
const showHealthCheck = ref(false);

const protocolItems = ref([
    'TCP', 'UDP', 'SCTP',
]);
const healthCheckItems = ref([
    {title: 'Default (let GKE decide)', value: ''},
    {title: 'HTTP', value: HealthCheckTypes.Http},
    {title: 'TCP - for ports that do not speak HTTP, eg. websockets', value: HealthCheckTypes.Tcp},
]);
const protocol = ref('');
const name = ref('');
const port = ref<number>();
const targetPort = ref<number>();
const healthCheckType = ref('');
const healthCheckPath = ref('');

const rules = {
    required: [
        (value: any) => {
            if (value) {
                return true;
            }
            return 'Field is required';
        }
    ]
};

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

onUnmounted(() => {
});

function render() {
    showProtocol.value = true;
    showTargetPort.value = true;

    // A HealthCheckPolicy is only acted on by a GKE Gateway, so there is nothing to configure elsewhere.
    showHealthCheck.value = System.Instance.hosting_provider === HostingProviders.Gke
        && props.input.deploymentSpecification.network_type === NetworkTypes.GatewayApi;

    protocol.value = props.input.servicePort.protocol ?? protocolItems.value[0];
    name.value = props.input.servicePort.name ?? '';
    port.value = props.input.servicePort.port;
    targetPort.value = props.input.servicePort.targetPort;
    healthCheckType.value = props.input.servicePort.healthCheckType ?? '';
    healthCheckPath.value = props.input.servicePort.healthCheckPath ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.servicePort.protocol = protocol.value;
    props.input.servicePort.name = name.value;
    props.input.servicePort.port = port.value!;
    props.input.servicePort.targetPort = targetPort.value!;
    props.input.servicePort.healthCheckType = healthCheckType.value;
    props.input.servicePort.healthCheckPath = healthCheckType.value === HealthCheckTypes.Http
        ? healthCheckPath.value
        : '';
    props.input.onSaveCallback();
    close();
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        v-model="showDialog">
        <v-form
            v-model="formIsValid"
        >
            <v-card
                class="w-100 h-100">
                <v-card-title>Service Port</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row>
                        <v-col
                            v-if="showProtocol"
                            cols="6">
                            <v-select
                                v-model="protocol"
                                variant="outlined"
                                label="Protocol"
                                :items="protocolItems"
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="name"
                                variant="outlined"
                                label="Name"
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="port"
                                variant="outlined"
                                label="Port"
                                :rules="rules.required"
                                type="number"
                            />
                        </v-col>
                        <v-col
                            v-if="showTargetPort"
                            cols="6">
                            <v-text-field
                                v-model.number="targetPort"
                                variant="outlined"
                                label="Target Port"
                                :rules="rules.required"
                                type="number"
                            />
                        </v-col>
                        <v-col
                            v-if="showHealthCheck"
                            cols="6">
                            <v-select
                                v-model="healthCheckType"
                                variant="outlined"
                                label="Health Check"
                                :items="healthCheckItems"
                                hint="A single TCP port forces the whole service onto a TCP health check, since one policy covers every port"
                                persistent-hint
                            />
                        </v-col>
                        <v-col
                            v-if="showHealthCheck && healthCheckType === HealthCheckTypes.Http"
                            cols="6">
                            <v-text-field
                                v-model="healthCheckPath"
                                variant="outlined"
                                label="Health Check Path"
                                placeholder="/"
                                hint="Request path GKE probes. Defaults to /"
                                persistent-hint
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-divider/>
                <v-card-actions>
                    <v-spacer/>
                    <v-btn
                        variant="tonal"
                        color="grey"
                        prepend-icon="fa fa-circle-xmark"
                        @click="onCloseBtnClicked">
                        Close
                    </v-btn>

                    <v-btn
                        flat
                        variant="tonal"
                        prepend-icon="fa fa-check"
                        color="green"
                        @click="onSaveBtnClicked">
                        Done
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-form>
    </v-dialog>
</template>

<style scoped>

</style>
