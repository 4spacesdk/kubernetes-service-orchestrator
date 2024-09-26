<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DeploymentSpecification, PostUpdateAction} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import PostUpdateActionEditButton
    from "@/components/Modules/Setup/PostUpdateActions/EditButton/PostUpdateActionEditButton.vue";

export interface DeploymentSpecificationUpdatePostUpdateActionsDialog_Input {
    deploymentSpecification: DeploymentSpecification;
}

interface Row {
    position: number;
    item: PostUpdateAction;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdatePostUpdateActionsDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: '', key: 'handle', sortable: false, width: 30},
    {title: 'Name', key: 'item.name', sortable: false},
    {title: 'Type', key: 'item.type', sortable: false},
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
    Api.deploymentSpecifications().get()
        .where('id', props.input.deploymentSpecification.id!)
        .include('deployment_specification_post_update_action')
        .find(value => {
            rows.value = value[0].deployment_specification_post_update_actions
                ?.sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                ?.map(relation => {
                    return {
                        position: relation.position ?? 0,
                        item: relation.post_update_action!,
                    }
                }) ?? [];
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
    bus.emit('postUpdateActionEdit', {
        postUpdateAction: PostUpdateAction.CreateDefault(),
        onSaveCallback: (item: PostUpdateAction) => {
            Api.postUpdateActions().getById(item.id!)
                .find(value => rows.value.push({
                    position: rows.value.length,
                    item: value[0]
                }));
        },
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('postUpdateActionEdit', {
        postUpdateAction: row.item,
        onSaveCallback: (item: PostUpdateAction) => {
            Api.postUpdateActions().getById(item.id!)
                .find(value => {
                    const index = rows.value.indexOf(row);
                    rows.value.splice(index, 1, {
                        position: row.position,
                        item: value[0]
                    });
                });
        },
    });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.deploymentSpecifications().updatePostUpdateActionsPutById(props.input.deploymentSpecification.id!);
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
                .sort((a, b) => a.position - b.position)
                .map(row => row.item.id!)
        },
        newItem => {
            bus.emit('deploymentSpecificationSaved', newItem);
            isSaving.value = false;
            close();
        });
}

function onCloseBtnClicked() {
    close();
}

function onSortChanged(event: CustomEvent) {
    const oldIndex = event.detail.oldIndex;
    const newIndex = event.detail.newIndex;

    const copy = [...rows.value].sort((a, b) => a.position - b.position);
    const movedItem = copy.splice(oldIndex, 1)[0];
    copy.splice(newIndex, 0, movedItem);

    let pos = 0;
    copy.forEach(item => item.position = pos++);
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
                    <span class="my-auto">Post Update Actions</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deploymentSpecification.name }}</v-chip>

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
                    v-sortableDataTable
                    @sorted="onSortChanged"
                    density="compact">
                    <template v-slot:item.handle="{ item }">
                        <v-icon class="grabbable">fa fa-grip-vertical</v-icon>
                    </template>
                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end">
                            <v-menu
                                min-width="250">
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        variant="plain" color="primary" size="small" :icon="true"
                                    >
                                        <v-icon>fa fa-cog</v-icon>
                                        <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                                    </v-btn>
                                </template>
                                <post-update-action-edit-button
                                    :post-update-action="item.item"/>
                            </v-menu>
                            <v-btn
                                variant="plain" color="primary" size="small" :icon="true"
                                @click="onEditRowClicked(item)">
                                <v-icon>fa fa-pen</v-icon>
                                <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                            </v-btn>
                            <v-btn
                                variant="plain"
                                color="red" size="small" :icon="true"
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

.grabbable {
    cursor: move; /* fallback if grab cursor is unsupported */
    cursor: grab;
    cursor: -moz-grab;
    cursor: -webkit-grab;
}
</style>
