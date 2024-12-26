<script setup lang="ts">
import {onMounted, onUnmounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import type {DeploymentStep} from "@/core/services/Deploy/Api";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import DeploymentStepStatus from "@/components/Modules/Setup/Deployments/DeploymentStepStatus/DeploymentStepStatus.vue";
import bus from "@/plugins/bus";
import DeploymentEditButton from "@/components/Modules/Setup/Deployments/EditButton/DeploymentEditButton.vue";

export interface DeploymentResourceListDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentResourceListDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

interface Row {
    no: number;
    item: DeploymentStep;
    isLoadingDeploy: boolean;
    isLoadingTerminate: boolean;
    isLoadingKubernetesStatus: boolean;
    isLoadingKubernetesEvents: boolean;
}

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref<{
    readonly key?: string,
    readonly title?: string | undefined,
    readonly sortable?: boolean | undefined,
    readonly align?: "end" | "center" | "start" | undefined,
}[]>([
    {title: 'No.', key: 'no', sortable: false},
    {title: 'Step', key: 'item.name', sortable: false},
    {title: 'Status', key: 'status', sortable: false, align: 'end'},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(false);
const selectedRows = ref<Row[]>([]);
const isLoadingBatchDeploy = ref(false);
const isLoadingBatchTerminate = ref(false);

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
    Api.deployments().getDeploymentSpecificationGetById(props.input.deployment.id!)
        .find(specs => {
            rows.value = specs[0].deploymentSteps
                ?.map((step, i) => {
                    return {
                        no: i + 1,
                        item: step,
                        isLoadingDeploy: false,
                        isLoadingTerminate: false,
                        isLoadingKubernetesStatus: false,
                        isLoadingKubernetesEvents: false,
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

function onKubernetesStatusBtnClicked(row: Row) {
    row.isLoadingKubernetesStatus = true;
    const api = Api.deploymentSteps().getKubernetesStatusGetByIdentifier(row.item.identifier!)
        .deploymentId(props.input.deployment.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            try {
                bus.emit('json', {
                    title: `Failed to get status ${row.item.name}`,
                    body: JSON.parse(response.error),
                });
            } catch (e) {
                bus.emit('info', {
                    title: `Failed to get status ${row.item.name}`,
                    body: response.error
                });
            }
        }
        row.isLoadingKubernetesStatus = false;
        return false;
    });
    api.find(value => {
        row.isLoadingKubernetesStatus = false;
        bus.emit('json', {
            title: `${props.input.deployment.name}.${props.input.deployment.namespace}: ${row.item.name} Status`,
            body: value[0].value!,
        });
    });
}

function onKubernetesEventsBtnClicked(row: Row) {
    row.isLoadingKubernetesEvents = true;
    const api = Api.deploymentSteps().getKubernetesEventsGetByIdentifier(row.item.identifier!)
        .deploymentId(props.input.deployment.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            try {
                bus.emit('json', {
                    title: `Failed to fetch events for ${row.item.name}`,
                    body: JSON.parse(response.error),
                });
            } catch (e) {
                bus.emit('info', {
                    title: `Failed to fetch events for ${row.item.name}`,
                    body: response.error
                });
            }
        }
        row.isLoadingKubernetesEvents = false;
        return false;
    });
    api.find(value => {
        row.isLoadingKubernetesEvents = false;
        bus.emit('json', {
            title: `${props.input.deployment.name}.${props.input.deployment.namespace}: ${row.item.name} Events`,
            body: value[0].value!,
        });
    });
}

function onPreviewBtnClicked(row: Row) {
    bus.emit('deploymentResourcePreview', {
        deployment: props.input.deployment,
        step: row.item,
    });
}

function onDeployBtnClicked(row: Row, onFinish?: () => void, onError?: () => void) {
    row.isLoadingDeploy = true;
    const api = Api.deploymentSteps().deployPutByIdentifier(row.item.identifier!)
        .deploymentId(props.input.deployment.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            try {
                bus.emit('json', {
                    title: `Failed to deploy ${row.item.name}`,
                    body: JSON.parse(response.error),
                });
            } catch (e) {
                bus.emit('info', {
                    title: `Failed to deploy ${row.item.name}`,
                    body: response.error
                });
            }
        }
        row.isLoadingDeploy = false;
        if (onError) {
            onError();
        }
        return false;
    });
    api.save(null, value => {
        row.isLoadingDeploy = false;
        bus.emit('deployment_DeploymentStep_Status_Change', {
            deployment: props.input.deployment,
            step: row.item,
        });
        if (onFinish) {
            onFinish();
        }
    });
}

function onTerminateBtnClicked(row: Row) {
    bus.emit('confirm', {
        body: `Do you want to terminate <strong>${row.item.name}</strong>?`,
        confirmIcon: 'fa fa-skull',
        confirmColor: 'warning',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                doTerminateRow(row);
            }
        }
    });
}

function doTerminateRow(row: Row, onFinish?: () => void, onError?: () => void) {
    row.isLoadingTerminate = true;
    const api = Api.deploymentSteps().terminatePutByIdentifier(row.item.identifier!)
        .deploymentId(props.input.deployment.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        row.isLoadingTerminate = false;
        if (onError) {
            onError();
        }
        return false;
    });
    api.save(null, value => {
        row.isLoadingTerminate = false;
        bus.emit('deployment_DeploymentStep_Status_Change', {
            deployment: props.input.deployment,
            step: row.item,
        });
        if (onFinish) {
            onFinish();
        }
    });
}

function onBatchDeployBtnClicked() {
    isLoadingBatchDeploy.value = true;

    let index = 0;
    const onError = () => {
        isLoadingBatchDeploy.value = false;
    };
    const onContinue = () => {
        if (selectedRows.value.length == index) {
            isLoadingBatchDeploy.value = false;
            return;
        }
        setTimeout(() => runRow(selectedRows.value[index++]), 2000);
    };
    const runRow = (row: Row) => {
        onDeployBtnClicked(
            row,
            () => {
                onContinue();
            },
            () => {
                onError();
            }
        );
    };
    onContinue();
}

function onBatchTerminateBtnClicked() {
    bus.emit('confirm', {
        body: `Do you want to terminate <strong>${selectedRows.value.length} rows</strong>?`,
        confirmIcon: 'fa fa-skull',
        confirmColor: 'warning',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                isLoadingBatchTerminate.value = true;

                let index = selectedRows.value.length - 1;
                const onError = () => {
                    isLoadingBatchTerminate.value = false;
                };
                const onContinue = () => {
                    if (index < 0) {
                        isLoadingBatchTerminate.value = false;
                        return;
                    }
                    runRow(selectedRows.value[index--]);
                };
                const runRow = (row: Row) => {
                    doTerminateRow(
                        row,
                        () => {
                            onContinue();
                        },
                        () => {
                            onError();
                        }
                    );
                };
                onContinue();
            }
        }
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
        height="65vh"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading">
            <v-card-title>
                <div class="d-flex w-100">
                    <span class="my-auto">Resources</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deployment.name }}.{{ props.input.deployment.namespace }}</v-chip>

                    <div class="my-auto ml-auto d-flex justify-end gap-1">
                        <div>
                            <v-btn
                                :disabled="selectedRows.length === 0"
                                :loading="isLoadingBatchDeploy"
                                variant="flat"
                                color="green"
                                size="small"
                                @click="onBatchDeployBtnClicked()">
                                <v-icon>fa fa-circle-play</v-icon>
                                <v-tooltip activator="parent" location="bottom">Deploy selected</v-tooltip>
                            </v-btn>
                        </div>
                        <div>
                            <v-btn
                                :disabled="selectedRows.length === 0"
                                :loading="isLoadingBatchTerminate"
                                variant="flat"
                                color="red"
                                size="small"
                                @click="onBatchTerminateBtnClicked()">
                                <v-icon>fa fa-skull</v-icon>
                                <v-tooltip activator="parent" location="bottom">Terminate selected</v-tooltip>
                            </v-btn>
                        </div>
                    </div>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <v-data-table-server
                    v-model="selectedRows"
                    :headers="headers"
                    :items-length="itemCount"
                    :items="rows"
                    :loading="isLoading"
                    :items-per-page="-1"
                    :show-select="true"
                    return-object
                    class="table"
                    density="compact">

                    <template v-slot:item.no="{ item }">
                        <v-chip size="x-small" variant="outlined" color="grey">{{ item.no }}</v-chip>
                    </template>

                    <template v-slot:item.status="{ item }">
                        <deployment-step-status
                            :step="item.item"
                            :deployment="props.input.deployment"/>
                    </template>

                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end">
                            <div>
                                <v-btn
                                    :disabled="!item.item.hasKubernetesStatus"
                                    :loading="item.isLoadingKubernetesStatus"
                                    variant="plain" :color="'grey'" size="small" icon
                                    @click="onKubernetesStatusBtnClicked(item)">
                                    <v-icon>fa fa-signal</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Kubernetes Status</v-tooltip>
                                </v-btn>
                                <v-tooltip
                                    :disabled="item.item.hasKubernetesStatus"
                                    activator="parent" location="bottom">Not available for this step
                                </v-tooltip>
                            </div>
                            <div>
                                <v-btn
                                    :disabled="!item.item.hasKubernetesEvents"
                                    :loading="item.isLoadingKubernetesEvents"
                                    variant="plain" :color="'grey'" size="small" icon
                                    @click="onKubernetesEventsBtnClicked(item)">
                                    <v-icon>fa fa-calendar-days</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Kubernetes Events</v-tooltip>
                                </v-btn>
                                <v-tooltip
                                    :disabled="item.item.hasKubernetesEvents"
                                    activator="parent" location="bottom">Not available for this step
                                </v-tooltip>
                            </div>
                            <div>
                                <v-btn
                                    :disabled="!item.item.hasPreviewCommand"
                                    variant="plain" :color="'grey'" size="small" icon
                                    @click="onPreviewBtnClicked(item)">
                                    <v-icon>fa fa-file-code</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Preview</v-tooltip>
                                </v-btn>
                                <v-tooltip
                                    :disabled="item.item.hasPreviewCommand"
                                    activator="parent" location="bottom">Not available for this step
                                </v-tooltip>
                            </div>
                            <div>
                                <v-btn
                                    :disabled="!item.item.hasDeployCommand"
                                    :loading="item.isLoadingDeploy"
                                    variant="plain" icon
                                    :color="item.item.hasDeployCommand ? 'green' : 'grey'"
                                    size="small"
                                    @click="onDeployBtnClicked(item)">
                                    <v-icon>fa fa-circle-play</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Deploy</v-tooltip>
                                </v-btn>
                                <v-tooltip
                                    :disabled="item.item.hasDeployCommand"
                                    activator="parent" location="bottom">Not available for this step
                                </v-tooltip>
                            </div>
                            <div>
                                <v-btn
                                    :disabled="!item.item.hasTerminateCommand"
                                    :loading="item.isLoadingTerminate"
                                    icon
                                    variant="plain" :color="item.item.hasTerminateCommand ? 'red' : 'grey'"
                                    size="small"
                                    @click="onTerminateBtnClicked(item)">
                                    <v-icon>fa fa-skull</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Terminate</v-tooltip>
                                </v-btn>
                                <v-tooltip
                                    :disabled="item.item.hasTerminateCommand"
                                    activator="parent" location="bottom">Not available for this step
                                </v-tooltip>
                            </div>
                        </div>
                    </template>

                </v-data-table-server>
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-menu
                    min-width="250">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            variant="plain" color="primary" size="small">
                            <v-icon>fa fa-cog</v-icon>
                            <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                        </v-btn>
                    </template>
                    <deployment-edit-button
                        :deployment="props.input.deployment"/>
                </v-menu>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Close
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
