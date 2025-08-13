<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationUpdateHttpProxyRoutePathDialog_Input {
    httpProxyRoute: {
        path: string;
        port: number;
        protocol: string;
        timeoutPolicyIdle: string;
        timeoutPolicyResponse: string;
        timeoutPolicyIdleConnection: string;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateHttpProxyRoutePathDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const formIsValid = ref(false);

const path = ref('');
const port = ref<number>();
const protocolItems = ref([
    'tls', 'h2', 'h2c',
]);
const protocol = ref('');
const timeoutPolicyIdle = ref('');
const timeoutPolicyResponse = ref('');
const timeoutPolicyIdleConnection = ref('');

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
    path.value = props.input.httpProxyRoute.path ?? '';
    port.value = props.input.httpProxyRoute.port ?? '';
    protocol.value = props.input.httpProxyRoute.protocol ?? '';
    timeoutPolicyIdle.value = props.input.httpProxyRoute.timeoutPolicyIdle ?? '';
    timeoutPolicyResponse.value = props.input.httpProxyRoute.timeoutPolicyResponse ?? '';
    timeoutPolicyIdleConnection.value = props.input.httpProxyRoute.timeoutPolicyIdleConnection ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.httpProxyRoute.path = path.value;
    props.input.httpProxyRoute.port = port.value ?? 0;
    props.input.httpProxyRoute.protocol = protocol.value;
    props.input.httpProxyRoute.timeoutPolicyIdle = timeoutPolicyIdle.value;
    props.input.httpProxyRoute.timeoutPolicyResponse = timeoutPolicyResponse.value;
    props.input.httpProxyRoute.timeoutPolicyIdleConnection = timeoutPolicyIdleConnection.value;
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
                <v-card-title>Http Proxy Route</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row>
                        <v-col cols="6">
                            <v-text-field
                                v-model="path"
                                variant="outlined"
                                label="Path"
                                clearable
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="port"
                                type="number"
                                variant="outlined"
                                label="Port"
                                clearable
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-select
                                v-model="protocol"
                                variant="outlined"
                                label="Protocol"
                                :items="protocolItems"
                                clearable
                                hint="omit to fall back on service annotations"
                                persistent-hint
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="timeoutPolicyIdle"
                                variant="outlined"
                                label="Timeout Policy: Idle"
                                clearable
                                hint="Timeout for receiving a response from the server after processing a request from client. If not supplied, Envoy’s default value of 15s applies. More information can be found in Envoy’s documentation."
                                persistent-hint
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="timeoutPolicyResponse"
                                variant="outlined"
                                label="Timeout Policy: Response"
                                clearable
                                hint="Timeout for how long the proxy should wait while there is no activity during single request/response (for HTTP/1.1) or stream (for HTTP/2). Timeout will not trigger while HTTP/1.1 connection is idle between two consecutive requests. If not specified, there is no per-route idle timeout, though a connection manager-wide stream idle timeout default of 5m still applies. More information can be found in Envoy’s documentation."
                                persistent-hint
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="timeoutPolicyIdleConnection"
                                variant="outlined"
                                label="Timeout Policy: Idle Connection"
                                clearable
                                hint="Timeout for how long connection from the proxy to the upstream service is kept when there are no active requests. If not supplied, Envoy’s default value of 1h applies. More information can be found in Envoy’s documentation."
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
