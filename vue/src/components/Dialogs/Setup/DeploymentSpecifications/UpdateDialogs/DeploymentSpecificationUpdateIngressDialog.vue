<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationUpdateIngressDialog_Input {
    ingress: {
        ingressClass?: string;
        proxyBodySize?: number;
        proxyConnectTimeout?: number;
        proxyReadTimeout?: number;
        proxySendTimeout?: number;
        sslRedirect?: boolean;
        enableTls?: boolean;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateIngressDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const formIsValid = ref(false);

const ingressClass = ref('');
const ingressClassItems = ref([
    'nginx',
]);
const proxyBodySize = ref<number>();
const proxyConnectTimeout = ref<number>();
const proxyReadTimeout = ref<number>();
const proxySendTimeout = ref<number>();
const sslRedirect = ref<boolean>();
const enableTls = ref<boolean>();

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
    ingressClass.value = props.input.ingress.ingressClass ?? ingressClassItems.value[0];
    proxyBodySize.value = props.input.ingress.proxyBodySize ?? 50;
    proxyConnectTimeout.value = props.input.ingress.proxyConnectTimeout ?? 600;
    proxyReadTimeout.value = props.input.ingress.proxyReadTimeout ?? 600;
    proxySendTimeout.value = props.input.ingress.proxySendTimeout ?? 600;
    sslRedirect.value = props.input.ingress.sslRedirect ?? true;
    enableTls.value = props.input.ingress.sslRedirect ?? true;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.ingress.ingressClass = ingressClass.value;
    props.input.ingress.proxyBodySize = proxyBodySize.value;
    props.input.ingress.proxyConnectTimeout = proxyConnectTimeout.value;
    props.input.ingress.proxyReadTimeout = proxyReadTimeout.value;
    props.input.ingress.proxySendTimeout = proxySendTimeout.value;
    props.input.ingress.sslRedirect = sslRedirect.value;
    props.input.ingress.enableTls = enableTls.value;
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
                <v-card-title>Ingress</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row>
                        <v-col cols="12">
                            <v-select
                                v-model="ingressClass"
                                variant="outlined"
                                label="Ingress class"
                                :rules="rules.required"
                                :items="ingressClassItems"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="proxyBodySize"
                                variant="outlined"
                                label="Proxy body size"
                                suffix="megabyte"
                                clearable
                                type="number"
                                density="compact"
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="proxyConnectTimeout"
                                variant="outlined"
                                label="Proxy connection timeout"
                                suffix="seconds"
                                clearable
                                type="number"
                                density="compact"
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="proxyReadTimeout"
                                variant="outlined"
                                label="Proxy read timeout"
                                suffix="seconds"
                                clearable
                                type="number"
                                density="compact"
                                hide-details
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="proxySendTimeout"
                                variant="outlined"
                                label="Proxy send timeout"
                                suffix="seconds"
                                clearable
                                type="number"
                                density="compact"
                                hide-details
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-checkbox
                                v-model="sslRedirect"
                                variant="outlined"
                                label="SSL Redirect"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-checkbox
                                v-model="enableTls"
                                variant="outlined"
                                label="Enable TLS"
                                hide-details
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
