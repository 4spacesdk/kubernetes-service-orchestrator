<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import EnvironmentVariableValue from "@/components/Modules/Common/EnvironmentVariables/EnvironmentVariableValue.vue";
import {fromBulk, toBulk, toRow, type EnvironmentVariableRow} from "@/components/Modules/Common/EnvironmentVariables/environmentVariables";

type Row = EnvironmentVariableRow;

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const showBulkEdit = ref(false);
const bulkEditBtnText = computed(() => {
    return showBulkEdit.value ? 'Key-Value Edit' : 'Bulk Edit';
})
const bulkEditContent = ref('');
const bulkEditContentRowCount = ref(5);

const isLoading = ref(false);
const isSaving = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Value', key: 'value', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);

const {markSaved} = useUnsavedChanges(() => showBulkEdit.value ? fromBulk(bulkEditContent.value, rows.value) : rows.value);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deploymentSpecifications().get()
        .where('id', props.deploymentSpecification.id!)
        .include('deployment_specification_environment_variable')
        .find(value => {
            rows.value = value[0].deployment_specification_environment_variables
                ?.map(toRow) ?? [];
            itemCount.value = rows.value.length;
            if (showBulkEdit.value) {
                updateBulkEditContentFromRows();
                updateBulkEditContentRowCount();
            }
            isLoading.value = false;
            markSaved();
        });
}

function updateRowsFromBulkEdit() {
    rows.value = fromBulk(bulkEditContent.value, rows.value);
}

function updateBulkEditContentFromRows() {
    bulkEditContent.value = toBulk(rows.value);
}

function updateBulkEditContentRowCount() {
    const count = (bulkEditContent.value.match(/\n/g) || []).length;
    bulkEditContentRowCount.value = count < 5 ? 5 : count + 1;
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCreateBtnClicked() {
    const newItem: Row = {
        name: '',
        value: '',
        is_secret: false,
        has_value: false,
    };
    bus.emit('deploymentSpecificationUpdateEnvironmentVariable', {
        environmentVariable: newItem,
        onSaveCallback: () => {
            rows.value.push(newItem);
            itemCount.value++;
            if (showBulkEdit.value && !newItem.is_secret) {
                bulkEditContent.value = bulkEditContent.value.concat('\n', `${newItem.name}:${newItem.value}`);
                updateBulkEditContentRowCount();
            }
        }
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdateEnvironmentVariable', {
        environmentVariable: row,
        onSaveCallback: () => {

        }
    });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSave() {
    if (isSaving.value) {
        return;
    }
    isSaving.value = true;
    if (showBulkEdit.value) {
        updateRowsFromBulkEdit();
    }
    const api = Api.deploymentSpecifications().updateEnvironmentVariablesPutById(props.deploymentSpecification.id!);
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
        bus.emit('deploymentSpecificationSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
        isSaving.value = false;
        render();
    });
}

function onBulkEditBtnClicked() {
    if (showBulkEdit.value) {
        updateRowsFromBulkEdit();
    } else {
        updateBulkEditContentFromRows();
        updateBulkEditContentRowCount();
    }
    showBulkEdit.value = !showBulkEdit.value;
}

// </editor-fold>
</script>

<template>
    <page-section
        title="Environment Variables"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <template #actions>
            <v-btn
                variant="text"
                color="secondary"
                @click="onBulkEditBtnClicked">
                {{ bulkEditBtnText }}
            </v-btn>
            <v-btn
                icon
                variant="plain"
                color="secondary-ligthen-1"
                size="small"
                @click="onCreateBtnClicked()">
                <v-icon>fa fa-plus</v-icon>
                <v-tooltip activator="parent" location="bottom">Create</v-tooltip>
            </v-btn>
        </template>

        <div
            v-if="showBulkEdit && rows.some(row => row.is_secret)"
            class="text-caption text-medium-emphasis mb-2">
            Secret variables are left out here, and kept as they are.
        </div>
        <v-textarea
            v-if="showBulkEdit"
            v-model="bulkEditContent"
            variant="outlined"
            :no-resize="false"
            auto-grow
            max-rows="20"
            :rows="bulkEditContentRowCount"
            @update:modelValue="updateBulkEditContentRowCount()">
        </v-textarea>

        <v-data-table-server
            v-else
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :items-per-page="-1"
            class="table"
            density="compact">
            <template v-slot:item.value="{ item }">
                <environment-variable-value :variable="item"/>
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
                        color="red" size="small"
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

.v-textarea :deep(.v-field__input) {
    -webkit-mask-image: none !important;
    mask-image: none !important;
}
</style>
