<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {PostUpdateActionConditionTypes} from "@/constants";
import PodioFieldReferenceSelect
    from "@/components/Modules/Integrations/PodioIntegrations/PodioFieldReferenceSelect.vue";
import PodioFieldValueInput from "@/components/Modules/Integrations/PodioIntegrations/PodioFieldValueInput.vue";
import {PodioFieldReference, PostUpdateActionCondition} from "@/core/services/Deploy/models";

export interface PostUpdateActionUpdateConditionDialog_Input {
    condition: PostUpdateActionCondition;

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: PostUpdateActionUpdateConditionDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const type = ref('');
const podioFieldReference = ref<PodioFieldReference>();
const value = ref<string>();

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
    podioFieldReference.value = props.input.condition.podio_field_reference ?? new PodioFieldReference();
    value.value = props.input.condition.value ?? '';
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
    props.input.condition.podio_field_reference = podioFieldReference.value;
    props.input.condition.value = value.value;
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

                    <v-col
                        v-if="type == PostUpdateActionConditionTypes.PodioFieldEquals"
                        cols="12"
                    >
                        <podio-field-reference-select
                            v-model="podioFieldReference"
                            class="mt-1"
                            label="Podio Field"
                        />
                    </v-col>
                    <v-col
                        v-if="type == PostUpdateActionConditionTypes.PodioFieldEquals && podioFieldReference"
                        cols="12"
                    >
                        <podio-field-value-input
                            v-model="value"
                            class="mt-1"
                            label="Value"
                            :field="podioFieldReference"
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
