<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationUpdateHttpProxyRoutePathDialog_Input {
    httpProxyRoute: {
        path: string;
        port: number;
        protocol: string;
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
