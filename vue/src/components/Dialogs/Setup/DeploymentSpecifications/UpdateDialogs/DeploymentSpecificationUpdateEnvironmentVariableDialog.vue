<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {VTextField} from "vuetify/components/VTextField";

export interface DeploymentSpecificationUpdateEnvirontmentVariableDialog_Input {
    environmentVariable: {
        name: string;
        value: string;
    };

    onSaveCallback: () => void;
}

interface Variable {
    name: string;
    code: string;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateEnvirontmentVariableDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const name = ref('');
const value = ref('');

const showVariablesMenu = ref(false);
const variables = ref<Variable[]>([
    {
        name: "Namespace",
        code: "${namespace}"
    },
    {
        name: "Database Host",
        code: "${database.host}"
    },
    {
        name: "Database Name",
        code: "${database.name}"
    },
    {
        name: "Database User",
        code: "${database.user}"
    },
    {
        name: "Database Password",
        code: "${database.pass}"
    },
    {
        name: "Email Service Host",
        code: "${emailService.host}"
    },
    {
        name: "Email Service Port",
        code: "${emailService.port}"
    },
    {
        name: "Email Service User",
        code: "${emailService.user}"
    },
    {
        name: "Email Service Pass",
        code: "${emailService.pass}"
    },
    {
        name: "Email Service Sender",
        code: "${emailService.sender}"
    },
    {
        name: "Workspace Id",
        code: "${workspace.id}"
    },
    {
        name: "Workspace Name",
        code: "${workspace.name}"
    },
]);

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
    props.input.onSaveCallback();
    close();
}

function onCloseBtnClicked() {
    close();
}

function onVariableClicked(variable: Variable) {
    value.value += variable.code;
    showVariablesMenu.value = false;
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
                        <div
                            class="d-flex"
                        >
                            <v-text-field
                                v-model="value"
                                variant="outlined"
                                label="Value"
                                spellcheck="false"
                            />

                            <v-menu
                                v-model="showVariablesMenu"
                                :close-on-content-click="false"
                                left
                                min-width="250"
                                offset-y>
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        icon
                                        variant="plain"
                                        color="primary"
                                        size="small">
                                        <v-icon>fa fa-plus</v-icon>
                                        <v-tooltip activator="parent" location="bottom">Insert variable</v-tooltip>
                                    </v-btn>
                                </template>

                                <v-list
                                    class="list-items">
                                    <v-list-item
                                        v-for="(variable, i) in variables" :key="i"
                                        dense
                                        @click="onVariableClicked(variable)">
                                        <v-list-item-title>
                                            <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                                            <span class="ml-2">{{ variable.name }}</span>
                                        </v-list-item-title>
                                    </v-list-item>
                                </v-list>
                            </v-menu>
                        </div>
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
    </v-dialog>
</template>

<style scoped>

</style>
