<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {DeploymentPackage, DeploymentSpecification} from "@/core/services/Deploy/models";
import {WorkloadTypes} from "@/constants";

export interface DeploymentPackageUpdateDeploymentSpecificationsDialog_Input {
    deploymentPackage: DeploymentPackage;
}

interface Row {
    deploymentSpecification: DeploymentSpecification;
    defaultVersion?: string;
    defaultImagePullPolicy?: string;
    defaultAutoUpdateEnabled?: boolean,
    defaultAutoUpdateTagRegex?: string,
    defaultAutoUpdateRequireApproval?: boolean,
    defaultEnvironment?: string;
    defaultCpuRequest?: number;
    defaultCpuLimit?: number;
    defaultMemoryRequest?: number;
    defaultMemoryLimit?: number;
    defaultReplicas?: number;
    defaultKnativeConcurrencyLimitSoft?: number;
    defaultKnativeConcurrencyLimitHard?: number;
    defaultKnativeScheduledMinScaleIds: number[];
}

interface CreateItem {
    inUse: boolean;
    deploymentSpecification: DeploymentSpecification;
}

const props = defineProps<{ input: DeploymentPackageUpdateDeploymentSpecificationsDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Deployment Specification', key: 'deploymentSpecification.name', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isSaving = ref(false);

const showCreateMenu = ref(false);
const isLoadingCreateItems = ref(false);
const createItems = ref<CreateItem[]>([]);

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
        .include(`deployment_package_deployment_specification?include=${encodeURIComponent('deployment_specification,k_native_min_scale_schedule')}`)
        .find(value => {
            rows.value = value[0].deployment_package_deployment_specifications
                ?.map(deploymentSpecificationSettings => {
                    return {
                        deploymentSpecification: deploymentSpecificationSettings.deployment_specification!,
                        defaultVersion: deploymentSpecificationSettings.default_version,
                        defaultImagePullPolicy: deploymentSpecificationSettings.default_image_pull_policy,
                        defaultAutoUpdateEnabled: deploymentSpecificationSettings.default_auto_update_enabled,
                        defaultAutoUpdateTagRegex: deploymentSpecificationSettings.default_auto_update_tag_regex,
                        defaultAutoUpdateRequireApproval: deploymentSpecificationSettings.default_auto_update_require_approval,
                        defaultEnvironment: deploymentSpecificationSettings.default_environment,
                        defaultCpuRequest: deploymentSpecificationSettings.default_cpu_request,
                        defaultCpuLimit: deploymentSpecificationSettings.default_cpu_limit,
                        defaultMemoryRequest: deploymentSpecificationSettings.default_memory_request,
                        defaultMemoryLimit: deploymentSpecificationSettings.default_memory_limit,
                        defaultReplicas: deploymentSpecificationSettings.default_replicas,
                        defaultKnativeConcurrencyLimitSoft: deploymentSpecificationSettings.default_knative_concurrency_limit_soft,
                        defaultKnativeConcurrencyLimitHard: deploymentSpecificationSettings.default_knative_concurrency_limit_hard,
                        defaultKnativeScheduledMinScaleIds: deploymentSpecificationSettings.k_native_min_scale_schedules?.map(k => k.id!) ?? [],
                    }
                })
                ?.sort((a, b) => a.deploymentSpecification.name?.localeCompare(b.deploymentSpecification.name ?? '') ?? 0)
                ?? [];
            itemCount.value = rows.value.length;
            isLoading.value = false;

            isLoadingCreateItems.value = true;
            const usedDeploymentSpecificationIds = rows.value
                    ?.map(row => row.deploymentSpecification.id!)
                ?? []
            Api.deploymentSpecifications().get()
                .orderAsc('name')
                .find(items => {
                    createItems.value = items.map(item => {
                        return {
                            inUse: usedDeploymentSpecificationIds.includes(item.id!),
                            deploymentSpecification: item,
                        };
                    });
                    isLoadingCreateItems.value = false;
                });
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCreateItemBtnClicked(createItem: CreateItem) {
    const newItem = {
        deploymentSpecification: createItem.deploymentSpecification,
        defaultKnativeScheduledMinScaleIds: [],
    };
    switch (createItem.deploymentSpecification.workload_type) {
        case WorkloadTypes.Deployment:
        case WorkloadTypes.KNativeService:
        case WorkloadTypes.DaemonSet:
            bus.emit('deploymentPackageUpdateDeploymentSpecification', {
                settings: newItem,
                onSaveCallback: () => {
                    createItem.inUse = true;
                    rows.value.push(newItem);
                }
            });
            break;
        case WorkloadTypes.CustomResource:
            createItem.inUse = true;
            rows.value.push(newItem);
            break;
    }
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentPackageUpdateDeploymentSpecification', {
        settings: row,
        onSaveCallback: () => {

        }
    });
}

function onEditRowKNativeMinScaleSchedulesClicked(row: Row) {
    bus.emit('deploymentPackageUpdateDeploymentSpecificationKNativeMinScaleSchedules', {
        deploymentSpecificationName: row.deploymentSpecification.name,
        knativeMinScaleScheduleIds: row.defaultKnativeScheduledMinScaleIds,
        onSaveCallback: (ids: number[]) => {
            row.defaultKnativeScheduledMinScaleIds = ids;
        }
    });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
    const createItem = createItems.value
        .find(createItem => createItem.deploymentSpecification.id == row.deploymentSpecification.id);
    if (createItem) {
        createItem.inUse = false;
    }
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.deploymentPackages().updateDeploymentSpecificationsPutById(props.input.deploymentPackage.id!);
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
                    <span class="my-auto">Deployment Specifications</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deploymentPackage.name }}</v-chip>

                    <v-menu
                        v-model="showCreateMenu"
                        :close-on-content-click="false"
                        left
                        min-width="250"
                        offset-y>
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                icon
                                variant="plain"
                                color="secondary"
                                size="small">
                                <v-icon>fa fa-plus</v-icon>
                                <v-tooltip activator="parent" location="bottom">Create</v-tooltip>
                            </v-btn>
                        </template>

                        <v-list
                            class="list-items">
                            <div
                                v-for="(createItem, i) in createItems" :key="i"
                            >
                                <v-list-item
                                    dense
                                    :disabled="createItem.inUse"
                                    @click="onCreateItemBtnClicked(createItem)"
                                >
                                    <v-list-item-title>
                                        <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                                        <span class="ml-2">{{ createItem.deploymentSpecification.name }}</span>
                                    </v-list-item-title>
                                </v-list-item>
                                <v-tooltip
                                    v-if="createItem.inUse"
                                    activator="parent" location="bottom">
                                    Already in use
                                </v-tooltip>
                            </div>
                        </v-list>
                    </v-menu>
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
                                v-if="item.deploymentSpecification.workload_type !== WorkloadTypes.CustomResource"
                                variant="plain" color="primary" size="small"
                                @click="onEditRowClicked(item)">
                                <v-icon>fa fa-pen</v-icon>
                                <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                            </v-btn>
                            <v-btn
                                v-if="item.deploymentSpecification.workload_type == WorkloadTypes.KNativeService"
                                variant="plain" color="primary" size="small"
                                @click="onEditRowKNativeMinScaleSchedulesClicked(item)">
                                <v-icon>fa fa-clock</v-icon>
                                <v-tooltip activator="parent" location="bottom">Default KNative Min Scale Schedules</v-tooltip>
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
