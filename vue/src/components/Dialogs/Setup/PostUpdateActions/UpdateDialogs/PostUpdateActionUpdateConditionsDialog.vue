<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {PostUpdateAction, PostUpdateActionCondition} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface PostUpdateActionUpdateConditionsDialog_Input {
    postUpdateAction: PostUpdateAction;
}

const props = defineProps<{ input: PostUpdateActionUpdateConditionsDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<PostUpdateActionCondition[]>([]);
const headers = ref([
    {title: 'Type', key: 'type', sortable: false},
    {title: 'Value', key: 'value', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isSaving = ref(false);

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
    showDialog.value = true;

    isLoading.value = true;
    Api.postUpdateActions().get()
        .where('id', props.input.postUpdateAction.id!)
        .include(`post_update_action_condition?include=${encodeURIComponent(`podio_field_reference`)}`)
        .find(value => {
            rows.value = value[0].post_update_action_conditions ?? [];
            itemCount.value = rows.value.length;
            isLoading.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCreateBtnClicked() {
    const newItem = PostUpdateActionCondition.CreateDefault();
    bus.emit('postUpdateActionUpdateCondition', {
        condition: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: PostUpdateActionCondition) {
    bus.emit('postUpdateActionUpdateCondition', {
        condition: row,
        onSaveCallback: () => {

        }
    });
}

function onDeleteRowClicked(row: PostUpdateActionCondition) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.postUpdateActions().updateConditionsPutById(props.input.postUpdateAction.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save({
        values: rows.value
    }, newItem => {
        bus.emit('postUpdateActionSaved', newItem);
        isSaving.value = false;
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
            :loading="isLoading"
            :disabled="isLoading"
            class="w-100 h-100">
            <v-card-title>
                <div class="d-flex w-100">
                    <span class="my-auto">Conditions</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.postUpdateAction.name }}</v-chip>

                    <div class="my-auto ml-auto d-flex justify-end gap-1">
                        <v-btn
                            :icon="true"
                            variant="plain"
                            color="secondary"
                            size="small"
                            @click="onCreateBtnClicked()">
                            <v-icon>fa fa-plus</v-icon>
                            <v-tooltip activator="parent" location="bottom">Create</v-tooltip>
                        </v-btn>
                    </div>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <v-data-table-server
                    :headers="headers"
                    :items-length="itemCount"
                    :items="rows"
                    :items-per-page="-1"
                    class="table"
                    density="compact">
                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end gap-1">
                            <v-btn
                                variant="plain" color="primary" size="small"
                                @click="onEditRowClicked(item)">
                                <v-icon>fa fa-pen</v-icon>
                                <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                            </v-btn>
                            <v-btn
                                variant="plain"
                                color="red" size="small"
                                @click="onDeleteRowClicked(item)">
                                <v-icon>fa fa-trash</v-icon>
                                <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                            </v-btn>
                        </div>
                    </template>
                </v-data-table-server>
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
                    :loading="isSaving"
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="green"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}
</style>
