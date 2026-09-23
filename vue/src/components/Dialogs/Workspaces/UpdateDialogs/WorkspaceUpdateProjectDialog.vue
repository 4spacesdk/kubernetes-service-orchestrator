<script setup lang="ts">
import { ReferenceData } from "@/core/referenceData";
import { useDialogSave } from "@/composables/useDialogSave";
import {onMounted, onUnmounted, ref} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface WorkspaceUpdateProjectDialog_Input {
    workspace: Workspace;
}

const props = defineProps<{ input: WorkspaceUpdateProjectDialog_Input, events: DialogEventsInterface }>();

const { isSaving, save } = useDialogSave();

const used = ref(false);
const showDialog = ref(false);

const value = ref<number>(0);
const items = ref<{ id: number, name: string }[]>([]);
const isLoading = ref(false);

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
    value.value = props.input.workspace.project_id ?? 0;
    showDialog.value = true;

    isLoading.value = true;
    ReferenceData.projects().then(response => {
            items.value = [
                {id: 0, name: 'No project'},
                ...response.map(project => ({id: project.id!, name: project.name ?? ''})),
            ];
            isLoading.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = Api.workspaces().updateProjectIdPutById(props.input.workspace.id!)
        .value(value.value ?? 0)
    save(api, null, newItem => {
        bus.emit('workspaceSaved', newItem);
        close();
    });
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
            <v-card-title>Workspace: Project</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-select
                            v-model="value"
                            :loading="isLoading"
                            :items="items"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            label="Project"/>
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
                    :loading="isSaving"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
