<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

interface Row {
    name: string;
    value: string;
}

const props = defineProps<{
    deployment: Deployment
}>();

const isLoading = ref(false);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Value', key: 'value', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);

const {autoSave, markLoaded, saveNow} = useAutoSave({
    state: () => rows.value,
    validate: () => rows.value.some(row => !row.name.trim()) ? 'a label needs a name' : null,
    request: () => Api.deployments().updateLabelsPutById(props.deployment.id!),
    data: () => ({values: rows.value}),
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
        .include('label')
        .find(value => {
            rows.value = value[0].labels
                ?.map(label => {
                    return {
                        name: label.name ?? '',
                        value: label.value ?? '',
                    }
                }) ?? [];
            isLoading.value = false;
            markLoaded();
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCreateBtnClicked() {
    const newItem = {
        name: '',
        value: '',
    };
    bus.emit('deploymentUpdateLabel', {
        label: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentUpdateLabel', {
        label: row,
        onSaveCallback: () => {

        }
    });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
}


// </editor-fold>
</script>

<template>
    <page-section
        title="Labels"
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
            density="compact">
            <template v-slot:item.value="{ item }">
                <span
                    class="text-truncate d-inline-block"
                    style="max-width: 300px;">{{ item.value }}</span>
            </template>
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
                        color="error" size="small"
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
</style>
