<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DeploymentPackage, Workspace} from "@/core/services/Deploy/models";
import WorkspaceEditButton from "@/components/Modules/Workspaces/EditButton/WorkspaceEditButton.vue";
import WorkspaceDeploymentStatus
    from "@/components/Modules/Workspaces/WorkspaceDeploymentStatus/WorkspaceDeploymentStatus.vue";
import {EventEmitter} from "@/helpers/EventEmitter";
import debounce from 'lodash.debounce'
import WorkspaceDeploymentDomains
    from "@/components/Modules/Workspaces/WorkspaceDeploymentDomains/WorkspaceDeploymentDomains.vue";
import {it} from "vuetify/locale";

interface Row {
    workspace: Workspace;
    isLoadingDeleteBtn?: boolean;
}

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Name', key: 'workspace.name_readable', sortable: false},
    {title: 'Namespace', key: 'workspace.namespace', sortable: false},
    {title: 'Status', key: 'status', sortable: false},
    {title: 'Url', key: 'url', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const showCreateMenu = ref(false);
const deploymentPackages = ref<DeploymentPackage[]>([]);

const searchValue = ref('');

onMounted(() => {
    bus.on('workspaceSaved', onItemSaved);

    getItems(false, true);

    Api.deploymentPackages().get()
        .find(items => {
            deploymentPackages.value = items;
        });
});

onUnmounted(() => {
    bus.off('workspaceSaved', onItemSaved);
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

function onItemSaved() {
    getItems(true, true);
}

function getItems(doItems = true, doCount = false) {
    // Get options from DataTable
    const tableOptions: any = options.value;

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.workspaces().get();

    if (searchValue.value?.length) {
        api
            .search('aliases', searchValue.value)
            .search('name_readable', searchValue.value)
            .search('name_system', searchValue.value)
            .search('namespace', searchValue.value)
            .search('subdomain', searchValue.value);
    }

    if (doItems) {
        api
            .include('deployment')
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderAsc('name_readable')
            .find(items => {
                rows.value = items.map(item => {
                    return {
                        workspace: item,
                        isLoadingRequestSupportLogin: false,
                    };
                });
                isLoading.value = false;
            });
    }

    // Count total amount of items
    if (doCount) {
        api.count(count => {
            itemCount.value = count;
        });
    }
}

// <editor-fold desc="View function">

function onCreateItemBtnClicked(type: DeploymentPackage) {
    bus.emit('workspaceCreate', {
        deploymentPackage: type,
    });
}

function onDeployItemBtnClicked(item: Workspace) {
    bus.emit('confirm', {
        body: `Do you want to deploy <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-play',
        confirmColor: 'green',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                const workerProps = {
                    title: `Deploying ${item.name}`,
                    body: 'This may take a minute',
                    onFinishBody: 'All done',
                    onIsWorkingChangeEventEmitter: new EventEmitter<boolean>()
                };
                bus.emit('worker', workerProps);

                workerProps.onIsWorkingChangeEventEmitter.emit(true);
                const api = Api.workspaces()
                    .deployPutById(item.id!);
                api.setErrorHandler(response => {
                    if (response.error) {
                        workerProps.onFinishBody = response.error.replaceAll("\n", "<br>");
                    }
                    return true;
                });
                api.save(null, () => {
                    workerProps.onIsWorkingChangeEventEmitter.emit(false);
                    bus.emit('workspaceSaved');
                });
            }
        }
    });
}

function onTerminateItemBtnClicked(item: Workspace) {
    bus.emit('confirm', {
        body: `Do you want to terminate <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-skull',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                const workerProps = {
                    title: `Terminating ${item.name}`,
                    body: 'This may take a minute',
                    onFinishBody: 'All done',
                    onIsWorkingChangeEventEmitter: new EventEmitter<boolean>()
                };
                bus.emit('worker', workerProps);

                workerProps.onIsWorkingChangeEventEmitter.emit(true);
                const api = Api.workspaces()
                    .terminatePutById(item.id!);
                api.setErrorHandler(response => {
                    if (response.error) {
                        workerProps.onFinishBody = response.error.replaceAll("\n", "<br>");
                    }
                    return true;
                });
                api.save(null, () => {
                    workerProps.onIsWorkingChangeEventEmitter.emit(false);
                    bus.emit('workspaceSaved');
                });
            }
        }
    });
}

function onDeleteItemBtnClicked(item: Row) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.workspace.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                item.isLoadingDeleteBtn = true;
                Api.workspaces().get()
                    .where('id', item.workspace.id!)
                    .include('deployment')
                    .find(workspaces => {
                        item.isLoadingDeleteBtn = false;

                        if (workspaces[0].deployments?.length) {
                            bus.emit('toast', {
                                text: 'All deployments must be terminated and deleted before you can delete the workspace'
                            });
                        } else {
                            Api.workspaces().deleteById(item.workspace.id!).delete(() => bus.emit('workspaceSaved'));
                        }
                    });
            }
        }
    });
}

function onShowMigrationJobsBtnClicked(item: Workspace) {
    bus.emit('migrationJobList', {
        workspace: item,
    });
}

function onShowDeploymentsBtnClicked(item: Workspace) {
    bus.emit('workspaceDeploymentList', {
        workspace: item,
    });
}

function onShowLogsBtnClicked(item: Workspace) {
    bus.emit('workspaceLogs', {
        workspace: item,
    });
}

// </editor-fold>

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>Workspaces</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>

            <v-menu
                v-model="showCreateMenu"
                :close-on-content-click="false"
                left
                min-width="250"
                offset-y>
                <template v-slot:activator="{ props }">
                    <v-btn
                        v-bind="props"
                        small
                        prepend-icon="fa fa-plus">
                        Create
                    </v-btn>
                </template>

                <v-list
                    class="list-items">
                    <v-list-item
                        v-for="(type, i) in deploymentPackages" :key="i"
                        dense
                        @click="onCreateItemBtnClicked(type)">
                        <v-list-item-title>
                            <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                            <span class="ml-2">{{ type.name }}</span>
                        </v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-menu>
        </v-toolbar>

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :loading="isLoading"
            :items-per-page="-1"
            class="table"
            density="compact"
            @update:options="options = $event; getItems()">

            <template v-slot:item.status="{ item }">
                <WorkspaceDeploymentStatus
                    :workspace="item.raw.workspace"/>
            </template>

            <template v-slot:item.url="{ item }">
                <workspace-deployment-domains
                    :workspace="item.raw.workspace"
                />
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowDeploymentsBtnClicked(item.raw.workspace)">
                        <v-icon>fa fa-box</v-icon>
                        <v-tooltip activator="parent" location="bottom">Deployments</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowLogsBtnClicked(item.raw.workspace)">
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Kubernetes Logs</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowMigrationJobsBtnClicked(item.raw.workspace)">
                        <v-icon>fa fa-truck-arrow-right</v-icon>
                        <v-tooltip activator="parent" location="bottom">Migration Jobs</v-tooltip>

                    </v-btn>

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
                        <WorkspaceEditButton
                            :workspace="item.raw.workspace"/>
                    </v-menu>

                    <v-btn
                        variant="plain" color="warning" size="small" icon
                        @click="onDeployItemBtnClicked(item.raw.workspace)">
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom">Deploy</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red" size="small" icon
                        @click="onTerminateItemBtnClicked(item.raw.workspace)">
                        <v-icon>fa fa-skull</v-icon>
                        <v-tooltip activator="parent" location="bottom">Terminate</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red" size="small" icon
                        @click="onDeleteItemBtnClicked(item.raw)"
                        :loading="item.raw.isLoadingDeleteBtn"
                    >
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>
    </div>
</template>

<style scoped>
.table > *,
.table {
    background: transparent;
}
</style>
