<script setup lang="ts">
import {useDialogSave} from "@/composables/useDialogSave";
import {onMounted, ref} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import DeploymentSpecificationForm from "@/components/Modules/Setup/DeploymentSpecifications/Form/DeploymentSpecificationForm.vue";

export interface DeploymentSpecificationCreateDialog_Input {
    deploymentSpecification: DeploymentSpecification;
    /** The saved specification. Editing it further happens on its page. */
    onCreated?: (item: DeploymentSpecification) => void;
}

const props = defineProps<{ input: DeploymentSpecificationCreateDialog_Input, events: DialogEventsInterface }>();

const {form, isSaving, save} = useDialogSave();

const showDialog = ref(false);
const item = ref<DeploymentSpecification>(new DeploymentSpecification());

onMounted(() => {
    item.value = props.input.deploymentSpecification;
    showDialog.value = true;
});

function close() {
    showDialog.value = false;
    props.events.onClose();
}

function onSaveBtnClicked() {
    (form.value as any)?.prepareForSave();

    save(Api.deploymentSpecifications().post(), item.value, newItem => {
        bus.emit('deploymentSpecificationSaved', newItem);
        close();
        props.input.onCreated?.(newItem);
    });
}

function onCloseBtnClicked() {
    close();
}

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        v-model="showDialog">
        <v-card class="w-100 h-100">
            <v-card-title>Deployment Specification</v-card-title>
            <v-divider/>
            <v-card-text class="mb-4">
                <deployment-specification-form
                    ref="form"
                    :item="item"/>
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
                    :loading="isSaving"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
