<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DeploymentPackage} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentPackageUpdateEnvironmentVariablesDialog_Input {
    deploymentPackage: DeploymentPackage;
}

interface Row {
    name: string;
    value: string;

    isLoadingCopyToDeploymentsBtn?: boolean;
}

const props = defineProps<{ input: DeploymentPackageUpdateEnvironmentVariablesDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Value', key: 'value', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isSaving = ref(false);
const showCopyToDeploymentsDialog = ref(false);
const copyToDeploymentsDialog_Row = ref<Row>();

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
    Api.deploymentPackages().get()
        .where('id', props.input.deploymentPackage.id!)
        .include('deployment_package_environment_variable')
        .find(value => {
            rows.value = value[0].deployment_package_environment_variables
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

function onCreateBtnClicked() {
    const newItem = {
        name: '',
        value: '',
    };
    bus.emit('deploymentPackageUpdateEnvironmentVariable', {
        environmentVariable: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentPackageUpdateEnvironmentVariable', {
        environmentVariable: row,
        onSaveCallback: () => {

        }
    });
}

function onCopyToDeploymentsClicked(row: Row) {
    copyToDeploymentsDialog_Row.value = row;
    showCopyToDeploymentsDialog.value = true;
}

function onCopyToDeploymentsConfirmed(row: Row, overwrite: boolean) {
    showCopyToDeploymentsDialog.value = false;
    row.isLoadingCopyToDeploymentsBtn = true;
    Api.deploymentPackages().copyEnvironmentVariableToDeploymentsPutByDeploymentPackageId(props.input.deploymentPackage.id!)
        .name(row.name)
        .value(row.value)
        .override(overwrite)
        .save(null, _ => {
            bus.emit('toast', {
                text: 'Environment variable copied to deployments'
            });
            row.isLoadingCopyToDeploymentsBtn = false;
        });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.deploymentPackages().updateEnvironmentVariablesPutById(props.input.deploymentPackage.id!);
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
        bus.emit('deploymentPackageSaved', newItem);
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
                    <span class="my-auto">Environment Variables</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deploymentPackage.name }}</v-chip>

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
                    density="compact">
                    <template v-slot:item.value="{ item }">
                        <span
                            class="text-truncate d-inline-block mt-1"
                            style="max-width: 300px;">{{ item.value }}</span>
                    </template>
                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end">
                            <v-btn
                                variant="plain" color="primary" size="small"
                                @click="onCopyToDeploymentsClicked(item)"
                                :loading="item.isLoadingCopyToDeploymentsBtn"
                            >
                                <v-icon>fa fa-copy</v-icon>
                                <v-tooltip activator="parent" location="bottom">Copy to deployments</v-tooltip>
                            </v-btn>
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

                <v-dialog
                    ref="dialog"
                    persistent
                    width="60vw"
                    v-model="showCopyToDeploymentsDialog">
                    <v-card
                        class="w-100 h-100">
                        <v-card-title>Copy environment variable</v-card-title>
                        <v-divider/>
                        <v-card-text>
                            If a deployment already has an environment variable named "{{copyToDeploymentsDialog_Row!.name}}", do you want to overwrite or skip?
                        </v-card-text>
                        <v-divider/>
                        <v-card-actions>
                            <v-btn
                                variant="tonal"
                                prepend-icon="fa fa-circle-xmark"
                                color="grey"
                                @click="showCopyToDeploymentsDialog = false">
                                Cancel
                            </v-btn>
                            <v-spacer/>
                            <v-btn
                                variant="tonal"
                                color="red"
                                @click="onCopyToDeploymentsConfirmed(copyToDeploymentsDialog_Row!, true)">
                                Overwrite
                            </v-btn>
                            <v-btn
                                flat
                                variant="tonal"
                                color="red"
                                @click="onCopyToDeploymentsConfirmed(copyToDeploymentsDialog_Row!, false)">
                                Skip
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
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
