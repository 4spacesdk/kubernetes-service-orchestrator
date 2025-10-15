<script setup lang="ts">
import {computed, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, KNativeMinScaleSchedule} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentUpdateKNativeMinScaleSchedulesDialog_Input {
    deployment: Deployment;
}

interface Row {
    item: KNativeMinScaleSchedule;
}

const props = defineProps<{
    input: DeploymentUpdateKNativeMinScaleSchedulesDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: '', key: 'handle', sortable: false, width: 30},
    {title: 'Priority', key: 'item.priority', sortable: false},
    {title: 'Min scale', key: 'item.min_scale', sortable: false},
    {title: 'Cron', key: 'item.cron_expression', sortable: false},
    {title: 'Description', key: 'item.description', sortable: false},
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
    Api.deployments().get()
        .where('id', props.input.deployment.id!)
        .include('k_native_min_scale_schedule')
        .find(value => {
            rows.value = value[0].k_native_min_scale_schedules
                ?.sort((a, b) => (a.priority ?? 0) - (b.priority ?? 0))
                ?.map(knativeMinScaleSchedule => {
                    return {
                        item: knativeMinScaleSchedule,
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
    bus.emit('knativeMinScaleScheduleEdit', {
        knativeMinScaleSchedule: KNativeMinScaleSchedule.CreateDefault(rows.value.length + 1),
        onSaveCallback: (item: KNativeMinScaleSchedule) => {
            rows.value.push({item: item});
        },
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('knativeMinScaleScheduleEdit', {
        knativeMinScaleSchedule: row.item,
        onSaveCallback: (item: KNativeMinScaleSchedule) => {
            const index = rows.value.indexOf(row);
            rows.value.splice(index, 1, {item: item});
        },
    });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.deployments().updateKNativeMinScaleSchedulesPutById(props.input.deployment.id!);
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
            values: rows.value.map(row => row.item.id!)
        },
        newItem => {
            bus.emit('deploymentSaved', newItem);
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

    const copy = [...rows.value].sort((a, b) => a.item.priority! - b.item.priority!);
    const movedItem = copy.splice(oldIndex, 1)[0];
    copy.splice(newIndex, 0, movedItem);

    let pos = 1;
    copy.forEach(item => item.item.priority = pos++);

    // Save
    rows.value.forEach(row => {
        Api.kNativeMinScaleSchedules().patchById(row.item.id!)
            .save({priority: row.item.priority} as any);
    });
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
                    <span class="my-auto">KNative Min Scale Schedules</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deployment.name }}</v-chip>

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
                            <v-btn
                                variant="plain" color="primary" size="small" icon
                                @click="onEditRowClicked(item)">
                                <v-icon>fa fa-pen</v-icon>
                                <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                            </v-btn>
                            <v-btn
                                variant="plain"
                                color="red" size="small" icon
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
