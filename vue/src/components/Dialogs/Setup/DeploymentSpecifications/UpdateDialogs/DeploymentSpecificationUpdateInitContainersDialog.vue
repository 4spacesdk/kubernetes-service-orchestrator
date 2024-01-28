<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DeploymentSpecification, InitContainer} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import InitContainerEditButton from "@/components/Modules/Setup/InitContainers/EditButton/InitContainerEditButton.vue";

export interface DeploymentSpecificationUpdateInitContainersDialog_Input {
    deploymentSpecification: DeploymentSpecification;
}

interface Row {
    position: number;
    item: InitContainer;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateInitContainersDialog_Input,
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
    {title: 'Image', key: 'item.container_image.name', sortable: false},
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
        .include('deployment_specification_init_container')
        .find(value => {
            rows.value = value[0].deployment_specification_init_containers
                ?.sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                ?.map(initContainer => {
                    return {
                        position: initContainer.position ?? 0,
                        item: initContainer.init_container!,
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
    bus.emit('initContainerEdit', {
        initContainer: InitContainer.CreateDefault(),
        onSaveCallback: (item: InitContainer) => {
            Api.initContainers().getById(item.id!)
                .find(value => rows.value.push({
                    position: rows.value.length,
                    item: value[0]
                }));
        },
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('initContainerEdit', {
        initContainer: row.item,
        onSaveCallback: (item: InitContainer) => {
            Api.initContainers().getById(item.id!)
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
    const api = Api.deploymentSpecifications().updateInitContainersPutById(props.input.deploymentSpecification.id!);
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
                    <span class="my-auto">Init Containers</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deploymentSpecification.name }}</v-chip>

                    <div class="my-auto ml-auto d-flex justify-end gap-1">
                        <v-btn
                            icon
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
                                        variant="plain" color="primary" size="small" icon>
                                        <v-icon>fa fa-cog</v-icon>
                                        <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                                    </v-btn>
                                </template>
                                <init-container-edit-button
                                    :init-container="item.raw.item"/>
                            </v-menu>
                            <v-btn
                                variant="plain" color="primary" size="small" icon
                                @click="onEditRowClicked(item.raw)">
                                <v-icon>fa fa-pen</v-icon>
                                <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                            </v-btn>
                            <v-btn
                                variant="plain"
                                color="red" size="small" icon
                                @click="onDeleteRowClicked(item.raw)">
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
