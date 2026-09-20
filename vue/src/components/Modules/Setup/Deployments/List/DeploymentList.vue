<script setup lang="ts">
import { ReferenceData } from "@/core/referenceData";
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {Ref} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import { DeploymentStatusTypes } from "@/constants";
import {Deployment, DeploymentSpecification} from "@/core/services/Deploy/models";
import DeploymentEditButton from "@/components/Modules/Setup/Deployments/EditButton/DeploymentEditButton.vue";
import DeploymentStatus from "@/components/Modules/Setup/Deployments/DeploymentStatus/DeploymentStatus.vue";
import DeploymentLastMigrationStatus
    from "@/components/Modules/Setup/Deployments/DeploymentLastMigrationStatus/DeploymentLastMigrationStatus.vue";
import DateView from "@/components/Modules/Common/DateView.vue";
import debounce from "lodash.debounce";
import DeploymentPodsButton from "@/components/Modules/Setup/Deployments/DeploymentPodsButton/DeploymentPodsButton.vue";
import {useRouter} from 'vue-router';

const props = defineProps<{
    filterByWorkspaceId?: number;

    showHeader: boolean;
    showCreateBtn?: boolean;
}>();

const emit = defineEmits<{
    (e: 'onItemDeleted', item: Deployment): void;
    (e: 'onItemSaved', item: Deployment): void;
}>();

const router = useRouter();

const itemCount = ref(0);
const rows = ref<Deployment[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Namespace', key: 'namespace', sortable: true},
    {title: 'Status', key: 'status', sortable: true},
    {title: 'Last Migration', key: 'last-migration', sortable: false},
    {title: 'Version', key: 'version', sortable: true},
    {title: 'Last Update', key: 'last_updated', sortable: true},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const showCreateMenu = ref(false);
/** Ids of the rows ticked for a bulk update. */
const selected = ref<number[]>([]);
const deploymentSpecs = ref<DeploymentSpecification[]>([]);
const showDeploymentSpecsWarning = ref(true);

/**
 * The same default as the workspaces list: a terminated workspace's deployments are out of
 * the way until someone asks for them. Only on the page - inside a workspace's own dialog the
 * list is that workspace's, and hiding half of it there would be a surprise.
 */
const statusOptions = ref([
    {value: DeploymentStatusTypes.Draft, title: 'Draft'},
    {value: DeploymentStatusTypes.Deploying, title: 'Deploying'},
    {value: DeploymentStatusTypes.Active, title: 'Active'},
    {value: DeploymentStatusTypes.Inactive, title: 'Inactive'},
    {value: DeploymentStatusTypes.Error, title: 'Error'},
]);
const selectedStatus = ref([DeploymentStatusTypes.Deploying, DeploymentStatusTypes.Active, DeploymentStatusTypes.Error]);
const filtersOnTheList: Record<string, Ref<string[]>> = props.filterByWorkspaceId ? {} : {status: selectedStatus};

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {'name': 'name', 'namespace': 'namespace', 'status': 'status', 'version': 'version', 'last_updated': 'last_updated'},
    defaultSort: {key: 'name', order: 'asc'},
    filters: filtersOnTheList,
    syncWithUrl: !props.filterByWorkspaceId,
});

onMounted(() => {
    bus.on('deploymentSaved', onItemSaved);

    getItems(false, true);

    if (deploymentSpecs.value.length == 0) {
        ReferenceData.deploymentSpecificationsWithImage().then(items => {
                deploymentSpecs.value = items;
                showDeploymentSpecsWarning.value = deploymentSpecs.value.length === 0;
            });
    }
});

onUnmounted(() => {
    bus.off('deploymentSaved', onItemSaved);
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

watch(selectedStatus, debounce(() => {
    getItems(true, true);
}, 500));

function onItemSaved() {
    getItems(true, true);
}

function getItems(doItems = true, doCount = false) {

    // Mark as Loading
    isLoading.value = true;

    // Prepare api call
    const api = Api.deployments().get();

    if (props.filterByWorkspaceId) {
        api.where('workspace_id', props.filterByWorkspaceId);
    } else {
        api.whereIn('status', selectedStatus.value);
    }

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('namespace', searchValue.value)
            .search('status', searchValue.value)
            .search('version', searchValue.value);
    }

    if (doItems) {
        api.include('workspace');
        applyPaging(api);
        applyOrdering(api);

        api
            .find(items => {
                rows.value = items;
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

function onCreateItemBtnClicked(deploymentSpec: DeploymentSpecification) {
    showCreateMenu.value = false;
    bus.emit('deploymentCreate', {
        spec: deploymentSpec,
        onSavedCallback: (deployment: Deployment) => emit('onItemSaved', deployment),
    });
}

function onDeleteItemBtnClicked(item: Deployment) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.deployments().deleteById(item.id!).delete(() => bus.emit('deploymentSaved'));
                emit('onItemDeleted', item);
            }
        }
    });
}

function onShowResourcesBtnClicked(item: Deployment) {
    bus.emit('deploymentResourceList', {
        deployment: item,
    });
}

function onShowMigrationJobsBtnClicked(item: Deployment) {
    bus.emit('migrationJobList', {
        deployment: item,
    });
}

function onDeploymentSpecsShortcutClicked() {
    router.push({name: 'DeploymentSpecifications'}).catch((e: any) => {
    });
}

function onBulkUpdateVersionBtnClicked() {
    bus.emit('deploymentBulkUpdateVersion', {
        deployments: rows.value.filter(row => selected.value.includes(row.id!)),
    });
}

// </editor-fold>

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            v-if="props.showHeader"
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
            :height="props.filterByWorkspaceId ? undefined : 120"
        >
            <!-- Two rows, as on the workspaces list: the status chips need a line of their
                 own, and squeezing them in beside the title cuts it off. -->
            <div class="d-flex flex-column w-100 py-2 px-4 ga-1">
                <div class="d-flex">
                    <v-toolbar-title class="my-auto">Deployments</v-toolbar-title>

                    <v-spacer></v-spacer>

                <v-menu
                    v-if="props.showCreateBtn"
                    v-model="showCreateMenu"
                    :close-on-content-click="false"
                    left
                    min-width="250"
                    offset-y>
                    <template v-slot:activator="{ props }">
                        <v-btn data-shortcut="create"
                            v-bind="props"
                            small
                            prepend-icon="fa fa-plus">
                            Create
                        </v-btn>
                    </template>

                    <v-list
                        v-if="showDeploymentSpecsWarning"
                        class="list-items">
                        <v-list-item
                            dense>
                            <v-list-item-title>
                                <span class="font-italic">No Deployment Specification found.</span>
                            </v-list-item-title>
                        </v-list-item>
                        <v-list-item
                            dense
                            @click="onDeploymentSpecsShortcutClicked">
                            <v-list-item-title>
                                <v-icon size="small" class="my-auto">fa fa-circle-right</v-icon>
                                <span class="ml-2">Go to Deployment Specifications</span>
                            </v-list-item-title>
                        </v-list-item>
                    </v-list>

                    <v-list
                        v-else
                        class="list-items">
                        <v-list-item
                            v-for="(spec, i) in deploymentSpecs" :key="i"
                            dense
                            @click="onCreateItemBtnClicked(spec)">
                            <v-list-item-title>
                                <v-icon size="small" class="my-auto ml-2">fa fa-window-maximize fa</v-icon>
                                <span class="ml-2">{{ spec.name }}</span>
                            </v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
                </div>

                <div class="d-flex ga-2">
                    <v-text-field data-shortcut="search"
                        v-model="searchValue"
                        density="compact"
                        variant="outlined"
                        hide-details
                        placeholder="Search"
                        clearable
                        width="250"
                        max-width="250"
                    />

                    <v-select
                        v-if="!props.filterByWorkspaceId"
                        v-model="selectedStatus"
                        :items="statusOptions"
                        label="Status"
                        density="compact"
                        variant="outlined"
                        multiple
                        item-value="value"
                        item-title="title"
                        hide-details
                        chips
                        closable-chips
                        clearable
                        width="420"
                        max-width="420"
                    />
                </div>
            </div>
        </v-toolbar>

        <div v-if="selected.length" class="d-flex align-center ga-2 px-4 py-1 bulk-bar">
            <span class="text-body-2">{{ selected.length }} selected</span>
            <v-btn size="small" variant="tonal" color="primary" prepend-icon="fa fa-code-branch" @click="onBulkUpdateVersionBtnClicked">
                Update version
            </v-btn>
            <v-btn size="small" variant="text" @click="selected = []">Clear</v-btn>
        </div>

        <v-data-table-server
            v-model="selected"
            show-select
            item-value="id"
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :loading="isLoading"
            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            v-model:sort-by="sortBy"
            class="table"
            density="compact"
            @update:options="options = $event; getItems()">

            <template v-slot:item.name="{ item }">
                <!-- A deployment has no edit dialog; its settings menu is the nearest thing. -->
                <v-menu min-width="250">
                    <template v-slot:activator="{ props }">
                        <name-link v-bind="props">{{ item.name }}</name-link>
                    </template>
                    <deployment-edit-button :deployment="item"/>
                </v-menu>
            </template>
            <template v-slot:item.status="{ item }">
                <deployment-status
                    :deployment="item"/>
            </template>

            <template v-slot:item.last-migration="{ item }">
                <DeploymentLastMigrationStatus
                    v-if="item.canMigrate"
                    :deployment="item"/>
            </template>

            <template v-slot:item.last_updated="{ item }">
                <DateView
                    v-if="item.last_updated"
                    :date-string="item.last_updated"/>
            </template>

            <template v-slot:item.actions="{ item }">

                <div class="d-flex justify-end ga-1">

                    <v-menu
                        min-width="550">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary"
                                size="small"
                                density="comfortable"
                                icon
                            >
                                <v-icon>fa fa-server</v-icon>
                                <v-tooltip activator="parent" location="bottom">Pods</v-tooltip>
                            </v-btn>
                        </template>
                        <deployment-pods-button
                            :deployment="item"
                            :app="item.name"
                            role="app"
                        />
                    </v-menu>

                    <v-btn
                        variant="plain" color="primary" 
                        @click="onShowResourcesBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-box</v-icon>
                        <v-tooltip activator="parent" location="bottom">Resources</v-tooltip>
                    </v-btn>

                    <v-btn
                        :disabled="!item.canMigrate"
                        variant="plain" color="primary" 
                        @click="onShowMigrationJobsBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-truck-arrow-right</v-icon>
                        <v-tooltip activator="parent" location="bottom">Migration Jobs</v-tooltip>
                    </v-btn>

                    <v-menu
                        min-width="250">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary"
                                size="small"
                                density="comfortable"
                                icon
                            >
                                <v-icon>fa fa-cog</v-icon>
                                <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                            </v-btn>
                        </template>
                        <deployment-edit-button
                            :deployment="item"/>
                    </v-menu>

                    <v-btn
                        variant="plain" color="red" 
                        @click="onDeleteItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
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
.bulk-bar {
    background: rgba(var(--v-theme-primary), 0.08);
}

/* Eight columns and a select column: with the default padding the row actions are pushed
   out of view on a narrow window. */
.table :deep(td),
.table :deep(th) {
    padding-left: 8px !important;
    padding-right: 8px !important;
}

.table > *,
.table {
    background: transparent;
}
</style>
