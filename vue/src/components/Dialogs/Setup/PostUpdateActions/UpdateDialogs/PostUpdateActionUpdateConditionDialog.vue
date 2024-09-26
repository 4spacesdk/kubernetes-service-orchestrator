<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {PostUpdateActionConditionTypes} from "@/constants";

export interface PostUpdateActionUpdateConditionDialog_Input {
    condition: {
        type: string;
        integrationId: number,
        appId: string;
        appToken: string;
        fieldId: string;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: PostUpdateActionUpdateConditionDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const type = ref('');
const integrationId = ref(0);
const appId = ref('');
const appToken = ref('');
const fieldId = ref('');

const types = ref([
    {
        identifier: PostUpdateActionConditionTypes.PodioFieldEquals,
        name: "Podio Field Equals",
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
    type.value = props.input.condition.type ?? '';
    integrationId.value = props.input.condition.integrationId ?? 0;
    appId.value = props.input.condition.appId ?? '';
    appToken.value = props.input.condition.appToken ?? '';
    fieldId.value = props.input.condition.fieldId ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.condition.type = type.value;
    props.input.condition.integrationId = integrationId.value;
    props.input.condition.appId = appId.value;
    props.input.condition.appToken = appToken.value;
    props.input.condition.fieldId = fieldId.value;
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
            <v-card-title>Condition</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row>
                    <v-col cols="12">
                        <v-select
                            v-model="type"
                            :items="types"
                            item-title="name"
                            item-value="identifier"
                            variant="outlined"
                            label="Type"
                            density="compact"
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
    </v-dialog>
</template>

<style scoped>

</style>
