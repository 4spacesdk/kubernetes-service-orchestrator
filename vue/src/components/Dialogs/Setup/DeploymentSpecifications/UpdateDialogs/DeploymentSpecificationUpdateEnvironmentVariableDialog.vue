<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {VTextField} from "vuetify/components/VTextField";
import VariableBtn from "@/components/Modules/Common/VariableBtn.vue";
import EnvironmentVariableValueField from "@/components/Modules/Common/EnvironmentVariables/EnvironmentVariableValueField.vue";
import type {EnvironmentVariableRow} from "@/components/Modules/Common/EnvironmentVariables/environmentVariables";

export interface DeploymentSpecificationUpdateEnvironmentVariableDialog_Input {
    environmentVariable: EnvironmentVariableRow;

    onSaveCallback: () => void;
}

interface Variable {
    name: string;
    code: string;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateEnvironmentVariableDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const name = ref('');
const value = ref('');
const isSecret = ref(false);
const hasStoredSecret = ref(false);

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
    name.value = props.input.environmentVariable.name ?? '';
    value.value = props.input.environmentVariable.value ?? '';
    isSecret.value = !!props.input.environmentVariable.is_secret;
    hasStoredSecret.value = isSecret.value && !!props.input.environmentVariable.has_value;
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.environmentVariable.name = name.value;
    props.input.environmentVariable.value = value.value;
    props.input.environmentVariable.is_secret = isSecret.value;
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
        <v-card
            class="w-100 h-100">
            <v-card-title>Environment Variable</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row>
                    <v-col cols="12">
                        <v-text-field
                            v-model="name"
                            variant="outlined"
                            label="Name"
                            spellcheck="false"
                        />
                    </v-col>
                    <v-col cols="12">
                        <environment-variable-value-field
                            v-model="value"
                            v-model:secret="isSecret"
                            :name="name"
                            :has-stored-secret="hasStoredSecret"
                        >
                            <template v-slot:append>
                                <variable-btn
                                    @add-variable="item => value += item"
                                />
                            </template>
                        </environment-variable-value-field>
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
                    color="success"
                    @click="onSaveBtnClicked">
                    Done
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
