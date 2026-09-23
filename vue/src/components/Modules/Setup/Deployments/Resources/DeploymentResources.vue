<script setup lang="ts">
import {onMounted, onUnmounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import type {DeploymentStep} from "@/core/services/Deploy/Api";
import DeploymentStepStatus from "@/components/Modules/Setup/Deployments/DeploymentStepStatus/DeploymentStepStatus.vue";
import bus from "@/plugins/bus";
import {DeploymentStepLevels} from "@/constants";

/**
 * What a deployment is made of in the cluster, step by step, each to deploy, terminate or look
 * at on its own or in a batch. On the deployment's page, and in a dialog from the list.
 */
const props = defineProps<{
    deployment: Deployment;
}>();

interface Row {
    no: number;
    item: DeploymentStep;
    isLoadingDeploy: boolean;
    isLoadingTerminate: boolean;
    isLoadingKubernetesStatus: boolean;
    isLoadingKubernetesEvents: boolean;
}

const workspaceItemCount = ref(0);
const deploymentItemCount = ref(0);
const workspaceRows = ref<Row[]>([]);
const deploymentRows = ref<Row[]>([]);
const headers = ref<{
    readonly key?: string,
    readonly title?: string | undefined,
    readonly sortable?: boolean | undefined,
    readonly align?: "end" | "center" | "start" | undefined,
}[]>([
    {title: 'No.', key: 'no', sortable: false},
    {title: 'Step', key: 'item.name', sortable: false},
    {title: 'Status', key: 'status', sortable: false, align: 'start'},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(false);
const selectedWorkspaceRows = ref<Row[]>([]);
const selectedDeploymentRows = ref<Row[]>([]);
const isLoadingBatchDeploy = ref(false);
const isLoadingBatchTerminate = ref(false);
const isMissingWorkspace = ref(false);
/** The deployment's cron jobs, for Run. Loaded the first time the menu opens. */
const cronJobNames = ref<string[] | null>(null);
const isLoadingCronJobNames = ref(false);
const runningCronJob = ref<string | null>(null);
const isRunMenuOpen = ref(false);

// <editor-fold desc="Functions">

onMounted(() => {
    render();

    bus.on('deploymentSaved', render);
});

onUnmounted(() => {
    bus.off('deploymentSaved', render);
});

function render() {
    isLoading.value = true;

    isMissingWorkspace.value = !props.deployment.workspace_id;

    Api.deployments().getDeploymentSpecificationGetById(props.deployment.id!)
        .find(specs => {
            let i = 1;
            workspaceRows.value = specs[0].deploymentSteps
                ?.filter(step => step.level == DeploymentStepLevels.Workspace)
                ?.map(step => {
                    return {
                        no: i++,
                        level: step.level,
                        item: step,
                        isLoadingDeploy: false,
                        isLoadingTerminate: false,
                        isLoadingKubernetesStatus: false,
                        isLoadingKubernetesEvents: false,
                    }
                }) ?? [];
            workspaceItemCount.value = workspaceRows.value.length;

            deploymentRows.value = specs[0].deploymentSteps
                ?.filter(step => step.level == DeploymentStepLevels.Deployment)
                ?.map(step => {
                    return {
                        no: i++,
                        item: step,
                        isLoadingDeploy: false,
                        isLoadingTerminate: false,
                        isLoadingKubernetesStatus: false,
                        isLoadingKubernetesEvents: false,
                    }
                }) ?? [];
            deploymentItemCount.value = deploymentRows.value.length;

            isLoading.value = false;
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onUpdateWorkspaceBtnClicked() {
    bus.emit('deploymentUpdateWorkspace', {
        deployment: props.deployment,
    });
}

function onKubernetesStatusBtnClicked(row: Row) {
    row.isLoadingKubernetesStatus = true;
    const api = Api.deploymentSteps().getKubernetesStatusGetByIdentifier(row.item.identifier!)
        .deploymentId(props.deployment.id!);
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
            title: `${props.deployment.name}.${props.deployment.namespace}: ${row.item.name} Status`,
            body: value[0].value!,
        });
    });
}

function onKubernetesEventsBtnClicked(row: Row) {
    row.isLoadingKubernetesEvents = true;
    const api = Api.deploymentSteps().getKubernetesEventsGetByIdentifier(row.item.identifier!)
        .deploymentId(props.deployment.id!);
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
            title: `${props.deployment.name}.${props.deployment.namespace}: ${row.item.name} Events`,
            body: value[0].value!,
        });
    });
}

function onPreviewBtnClicked(row: Row) {
    bus.emit('deploymentResourcePreview', {
        deployment: props.deployment,
        step: row.item,
    });
}

function onRunMenuToggled(open: boolean) {
    isRunMenuOpen.value = open;
    if (!open || cronJobNames.value !== null) {
        return;
    }
    isLoadingCronJobNames.value = true;
    const api = Api.deployments().getCronJobNamesGetById(props.deployment.id!);
    api.setErrorHandler(response => {
        bus.emit('info', {
            title: 'Failed to list the cron jobs',
            body: response.error ?? 'failed to load',
        });
        isLoadingCronJobNames.value = false;
        return false;
    });
    api.find(response => {
        cronJobNames.value = response[0]?.names ?? [];
        isLoadingCronJobNames.value = false;
    });
}

/**
 * Starts a job from the cron job's template in the cluster - what is deployed,
 * not what the next deploy would send.
 */
function onRunCronJobClicked(name: string) {
    runningCronJob.value = name;
    const api = Api.deployments().runCronJobPostById(props.deployment.id!)
        .name(name);
    api.setErrorHandler(response => {
        bus.emit('info', {
            title: `Failed to run ${name}`,
            body: response.error ?? 'failed to run',
        });
        runningCronJob.value = null;
        return false;
    });
    api.save(null, value => {
        bus.emit('toast', {
            text: `Started ${value.job}`,
        });
        runningCronJob.value = null;
    });
}

function onDeployBtnClicked(row: Row, onFinish?: () => void, onError?: () => void) {
    row.isLoadingDeploy = true;
    const api = Api.deploymentSteps().deployPutByIdentifier(row.item.identifier!)
        .deploymentId(props.deployment.id!);
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
            deployment: props.deployment,
            step: row.item,
        });
        if (onFinish) {
            onFinish();
        }
    });
}

function onTerminateBtnClicked(row: Row) {
    bus.emit('confirm', {
        body: `Do you want to terminate "${row.item.name}"?`,
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
        .deploymentId(props.deployment.id!);
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
            deployment: props.deployment,
            step: row.item,
        });
        if (onFinish) {
            onFinish();
        }
    });
}

function onBatchDeployBtnClicked() {
    isLoadingBatchDeploy.value = true;

    const selectedRows = [
        ...selectedWorkspaceRows.value,
        ...selectedDeploymentRows.value,
    ];

    let index = 0;
    const onError = () => {
        isLoadingBatchDeploy.value = false;
    };
    const onContinue = () => {
        if (selectedRows.length == index) {
            isLoadingBatchDeploy.value = false;
            return;
        }
        setTimeout(() => runRow(selectedRows[index++]), 2000);
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
    const selectedRows = [
        ...selectedWorkspaceRows.value,
        ...selectedDeploymentRows.value,
    ];

    bus.emit('confirm', {
        body: `Do you want to terminate ${selectedRows.length} rows?`,
        confirmIcon: 'fa fa-skull',
        confirmColor: 'warning',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                isLoadingBatchTerminate.value = true;

                let index = selectedRows.length - 1;
                const onError = () => {
                    isLoadingBatchTerminate.value = false;
                };
                const onContinue = () => {
                    if (index < 0) {
                        isLoadingBatchTerminate.value = false;
                        return;
                    }
                    runRow(selectedRows[index--]);
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

// </editor-fold>

</script>

<template>
    <div>
        <div class="batch d-flex justify-end gap-1">
            <div>
                <v-btn
                    :disabled="(selectedWorkspaceRows.length + selectedDeploymentRows.length) === 0"
                    :loading="isLoadingBatchDeploy"
                    variant="flat"
                    color="success"
                    size="small"
                    @click="onBatchDeployBtnClicked()">
                    <v-icon>fa fa-circle-play</v-icon>
                    <v-tooltip activator="parent" location="bottom">Deploy selected</v-tooltip>
                </v-btn>
            </div>
            <div>
                <v-btn
                    :disabled="(selectedWorkspaceRows.length + selectedDeploymentRows.length) === 0"
                    :loading="isLoadingBatchTerminate"
                    variant="flat"
                    color="error"
                    size="small"
                    @click="onBatchTerminateBtnClicked()">
                    <v-icon>fa fa-skull</v-icon>
                    <v-tooltip activator="parent" location="bottom">Terminate selected</v-tooltip>
                </v-btn>
            </div>
        </div>
        <v-data-table-server
            v-model="selectedWorkspaceRows"
            :headers="headers"
            :items-length="workspaceItemCount"
            :items="workspaceRows"
            :loading="isLoading"
            :items-per-page="-1"
            :show-select="true"
            return-object
            hide-default-footer
            class="table"
            density="compact">
            <template v-slot:top>
                <v-toolbar
                    flat
                    density="compact"
                >
                    <v-toolbar-title>
                        <div
                            class="d-flex pr-4"
                        >
                            <span class="my-auto">Workspace resources</span>
                            <v-chip
                                v-if="isMissingWorkspace"
                                class="ml-auto my-auto"
                                size="small"
                                @click="onUpdateWorkspaceBtnClicked"
                                color="error">missing workspace
                                <v-tooltip activator="parent" location="bottom">Select workspace</v-tooltip>
                            </v-chip>
                            <v-chip
                                v-if="!isMissingWorkspace"
                                class="ml-auto my-auto"
                                size="small"
                                color="success">{{ props.deployment.workspace?.name }}
                            </v-chip>
                        </div>
                    </v-toolbar-title>
                </v-toolbar>
            </template>

            <template v-slot:item.no="{ item }">
                <v-chip size="x-small" variant="outlined" color="grey">{{ item.no }}</v-chip>
            </template>

            <template v-slot:item.status="{ item }">
                <deployment-step-status
                    style="margin-left: -10px;"
                    :step="item.item"
                    :deployment="props.deployment"/>
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
                            :color="item.item.hasDeployCommand ? 'success' : 'grey'"
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
                            variant="plain" :color="item.item.hasTerminateCommand ? 'error' : 'grey'"
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

        <v-data-table-server
            v-model="selectedDeploymentRows"
            :headers="headers"
            :items-length="deploymentItemCount"
            :items="deploymentRows"
            :loading="isLoading"
            :items-per-page="-1"
            :show-select="true"
            return-object
            hide-default-footer
            class="table"
            density="compact">
            <template v-slot:top>
                <v-toolbar
                    flat
                    density="compact"
                >
                    <v-toolbar-title>Deployment resources</v-toolbar-title>
                </v-toolbar>
            </template>

            <template v-slot:item.no="{ item }">
                <v-chip size="x-small" variant="outlined" color="grey">{{ item.no }}</v-chip>
            </template>

            <template v-slot:item.status="{ item }">
                <deployment-step-status
                    style="margin-left: -10px;"
                    :step="item.item"
                    :deployment="props.deployment"/>
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
                    <div v-if="item.item.identifier === 'cronjob'">
                        <v-menu @update:model-value="onRunMenuToggled">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn
                                    v-bind="menuProps"
                                    :loading="runningCronJob !== null"
                                    variant="plain" color="primary" size="small" icon>
                                    <v-icon>fa fa-person-running</v-icon>
                                    <v-tooltip :disabled="isRunMenuOpen" activator="parent" location="bottom">Run a cron job now</v-tooltip>
                                </v-btn>
                            </template>
                            <v-list density="compact">
                                <v-list-item v-if="isLoadingCronJobNames" title="Loading..."/>
                                <v-list-item v-else-if="!cronJobNames?.length" title="No cron jobs"/>
                                <v-list-item
                                    v-for="name in cronJobNames ?? []" :key="name"
                                    :title="name"
                                    prepend-icon="fa fa-play"
                                    @click="onRunCronJobClicked(name)"/>
                            </v-list>
                        </v-menu>
                    </div>
                    <div>
                        <v-btn
                            :disabled="!item.item.hasDeployCommand"
                            :loading="item.isLoadingDeploy"
                            variant="plain" icon
                            :color="item.item.hasDeployCommand ? 'success' : 'grey'"
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
                            variant="plain" :color="item.item.hasTerminateCommand ? 'error' : 'grey'"
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
    </div>
</template>

<style scoped>
.batch {
    margin-bottom: 8px;
}

.table {
    min-height: 0 !important;
}
</style>
