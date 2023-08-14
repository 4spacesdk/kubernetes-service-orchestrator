<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationUpdateServicePortDialog_Input {
    servicePort: {
        protocol: string;
        name: string;
        port?: number;
        targetPort?: number;
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

const protocol = ref('');
const name = ref('');
const port = ref<number>();
const targetPort = ref<number>();

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
    protocol.value = props.input.servicePort.protocol ?? '';
    name.value = props.input.servicePort.name ?? '';
    port.value = props.input.servicePort.port;
    targetPort.value = props.input.servicePort.targetPort;
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
                        <v-col cols="6">
                            <v-text-field
                                v-model="protocol"
                                variant="outlined"
                                label="Protocol"
                                clearable
                                persistent-hint
                                hint="TCP, UDP, SCTP"
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
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="targetPort"
                                variant="outlined"
                                label="Target Port"
                                :rules="rules.required"
                                type="number"
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
                        :disabled="!formIsValid"
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
