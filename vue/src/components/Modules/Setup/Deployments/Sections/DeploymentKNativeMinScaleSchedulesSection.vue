<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment, KNativeMinScaleSchedule} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

interface Row {
    item: KNativeMinScaleSchedule;
}

const props = defineProps<{
    deployment: Deployment
}>();

const isLoading = ref(false);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: '', key: 'handle', sortable: false, width: 30},
    {title: 'Priority', key: 'item.priority', sortable: false},
    {title: 'Min scale', key: 'item.min_scale', sortable: false},
    {title: 'Cron', key: 'item.cron_expression', sortable: false},
    {title: 'Description', key: 'item.description', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);

// Schedules are saved by their own dialog and priorities as they are sorted, so what is saved
// here is which schedules the deployment has - when one is added or removed.
const {autoSave, markLoaded, saveNow} = useAutoSave({
    state: () => rows.value.map(row => row.item.id),
    request: () => Api.deployments().updateKNativeMinScaleSchedulesPutById(props.deployment.id!),
    data: () => ({values: rows.value.map(row => row.item.id!)}),
    onSaved: saved => bus.emit('deploymentSaved', saved),
    delay: 0,
});
defineExpose({saveNow});

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deployments().get()
        .where('id', props.deployment.id!)
        .include('k_native_min_scale_schedule')
        .find(value => {
            rows.value = value[0].k_native_min_scale_schedules
                ?.sort((a, b) => (a.priority ?? 0) - (b.priority ?? 0))
                ?.map(knativeMinScaleSchedule => {
                    return {
                        item: knativeMinScaleSchedule,
                    }
                }) ?? [];
            isLoading.value = false;
            markLoaded();
        });
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
    <page-section
        title="KNative Min Scale Schedules"
        flush
        :is-loading="isLoading"
        :auto-save="autoSave">
        <template #actions>
            <v-btn
                icon
                variant="plain"
                color="secondary"
                size="small"
                @click="onCreateBtnClicked()">
                <v-icon>fa fa-plus</v-icon>
                <v-tooltip activator="parent" location="bottom">Create</v-tooltip>
            </v-btn>
        </template>

        <v-data-table-server
            :headers="headers"
            :items-length="rows.length"
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
                        color="error" size="small" icon
                        @click="onDeleteRowClicked(item)">
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>
    </page-section>
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
