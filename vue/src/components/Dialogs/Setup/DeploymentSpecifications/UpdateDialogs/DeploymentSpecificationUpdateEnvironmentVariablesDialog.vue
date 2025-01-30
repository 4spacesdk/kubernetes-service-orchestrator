<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import debounce from 'lodash.debounce';

export interface DeploymentSpecificationUpdateEnvironmentVariablesDialog_Input {
    deploymentSpecification: DeploymentSpecification;
}

interface Row {
    name: string;
    value: string;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateEnvironmentVariablesDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
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
        .include('deployment_specification_environment_variable')
        .find(value => {
            rows.value = value[0].deployment_specification_environment_variables
                ?.map(environmentVariable => {
                    return {
                        name: environmentVariable.name ?? '',
                        value: environmentVariable.value ?? '',
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

function convertRowsToBulkEditContent(rows: Row[]): string {
    return rows.map(({name, value}) => `${name}:${value}`).join('\n');
}

function convertBulkEditToRows(bulkEditString: string): Row[] {
    return bulkEditString.split('\n').map(line => {
        const firstColonIndex = line.indexOf(':');
        if (firstColonIndex === -1) {
            return {name: line, value: ''};
        }
        return {
            name: line.substring(0, firstColonIndex),
            value: line.substring(firstColonIndex + 1)
        };
    });
}

function updateRowsFromBulkEdit() {
    rows.value = convertBulkEditToRows(bulkEditContent.value);
}

function updateBulkEditContentFromRows() {
    bulkEditContent.value = convertRowsToBulkEditContent(rows.value);
}

function updateBulkEditContentRowCount() {
    const count = (bulkEditContent.value.match(/\n/g) || []).length;
    bulkEditContentRowCount.value = count < 5 ? 5 : count + 1;
}

function onCreateBtnClicked() {
    const newItem = {
        name: '',
        value: '',
    };
    bus.emit('deploymentSpecificationUpdateEnvironmentVariable', {
        environmentVariable: newItem,
        onSaveCallback: () => {
            rows.value.push(newItem);
            itemCount.value++;
            if (showBulkEdit.value) {
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

function onSaveBtnClicked() {
    isSaving.value = true;
    if (showBulkEdit.value) {
        updateRowsFromBulkEdit();
    }
    const api = Api.deploymentSpecifications().updateEnvironmentVariablesPutById(props.input.deploymentSpecification.id!);
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
        isSaving.value = false;
        close();
    });
}

function onCloseBtnClicked() {
    close();
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

const onBulkEditUpdate = debounce(() => {
    updateBulkEditContentRowCount();
}, 300);

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
                    <span class="my-auto">Environment Variables</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deploymentSpecification.name }}</v-chip>

                    <div class="my-auto ml-auto d-flex justify-end gap-1">
                        <v-btn
                            style="margin-top: 2px"
                            variant="flat"
                            density="default"
                            slim flat
                            color="blue-grey-darken-2"
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
                    </div>
                </div>
            </v-card-title>

            <v-card-text class="px-4">
                <v-textarea
                    v-if="showBulkEdit"
                    v-model="bulkEditContent"
                    variant="outlined"
                    :no-resize="false"
                    auto-grow
                    max-rows="20"
                    :rows="bulkEditContentRowCount"
                    @update:modelValue="onBulkEditUpdate">
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
                        <span
                            class="text-truncate d-inline-block mt-1"
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
                                color="red" size="small"
                                @click="onDeleteRowClicked(item)">
                                <v-icon>fa fa-trash</v-icon>
                                <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                            </v-btn>
                        </div>
                    </template>
                </v-data-table-server>
            </v-card-text>

            <v-card-actions>
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

.v-textarea :deep(.v-field__input) {
    -webkit-mask-image: none !important;
    mask-image: none !important;
}
</style>
