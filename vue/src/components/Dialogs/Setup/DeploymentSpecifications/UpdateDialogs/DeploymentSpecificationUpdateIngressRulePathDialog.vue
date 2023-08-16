<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationUpdateIngressRulePathDialog_Input {
    ingressRulePath: {
        path: string;
        pathType?: string;
        backendServicePortName: string;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateIngressRulePathDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const formIsValid = ref(false);

const path = ref('');
const pathTypeItems = ref([
    'Prefix', 'ImplementationSpecific', 'Exact',
]);
const pathType = ref('');
const backendServicePortName = ref('');

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
    path.value = props.input.ingressRulePath.path ?? '';
    pathType.value = props.input.ingressRulePath.pathType ?? pathTypeItems.value[0];
    backendServicePortName.value = props.input.ingressRulePath.backendServicePortName ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.ingressRulePath.path = path.value;
    props.input.ingressRulePath.pathType = pathType.value;
    props.input.ingressRulePath.backendServicePortName = backendServicePortName.value;
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
                <v-card-title>Ingress Rule Path</v-card-title>
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
                            <v-select
                                v-model="pathType"
                                variant="outlined"
                                label="Path Type"
                                :rules="rules.required"
                                :items="pathTypeItems"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model="backendServicePortName"
                                variant="outlined"
                                label="Backend service port name"
                                clearable
                                :rules="rules.required"
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
