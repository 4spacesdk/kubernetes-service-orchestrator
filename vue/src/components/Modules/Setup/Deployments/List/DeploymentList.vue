<script setup lang="ts">
import { ReferenceData } from "@/core/referenceData";
import { useListState } from "@/composables/useListState";
import {useDeploymentActions} from "@/composables/useDeploymentActions";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import ListFilters from "@/components/Modules/Common/List/ListFilters.vue";
import {useDisplay} from "vuetify";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {Ref} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import { DeploymentStatusTypes, HealthStatusTypes } from "@/constants";
import {Deployment, DeploymentSpecification} from "@/core/services/Deploy/models";
import DeploymentStatus from "@/components/Modules/Setup/Deployments/DeploymentStatus/DeploymentStatus.vue";
import DeploymentHealth from "@/components/Modules/Setup/Deployments/DeploymentHealth/DeploymentHealth.vue";
import DeploymentLastMigrationStatus
    from "@/components/Modules/Setup/Deployments/DeploymentLastMigrationStatus/DeploymentLastMigrationStatus.vue";
import DateView from "@/components/Modules/Common/DateView.vue";
import debounce from "lodash.debounce";
import DeploymentPodsButton from "@/components/Modules/Setup/Deployments/DeploymentPodsButton/DeploymentPodsButton.vue";
import {useRouter} from 'vue-router';

const props = defineProps<{
    filterByWorkspaceId?: number;
    /** Only these deployments, e.g. the ones running a container image. */
    filterByIds?: number[];

    showHeader: boolean;
    showCreateBtn?: boolean;
}>();

const emit = defineEmits<{
    (e: 'onItemDeleted', item: Deployment): void;
    (e: 'onItemSaved', item: Deployment): void;
}>();

const router = useRouter();
const {deploy, terminate} = useDeploymentActions();

/**
 * A phone: the filters behind a button, and what is not acted on from a phone left out of
 * each row's card, so a card is not a page long - and so is choosing rows for the bulk update.
 */
const {xs: isPhone} = useDisplay();
const phoneHidden = ['last-migration', 'last_updated'];
const shownHeaders = computed(() => isPhone.value
    ? headers.value.filter(header => !phoneHidden.includes(header.key))
    : headers.value);

const itemCount = ref(0);
const rows = ref<Deployment[]>([]);
const headers = ref([
    // The namespace is under the name, as on the workspaces list: nine columns did not fit.
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Status', key: 'status', sortable: true},
    {title: 'Health', key: 'health', sortable: true},
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
    {value: DeploymentStatusTypes.OutOfSync, title: 'Out of sync'},
    {value: DeploymentStatusTypes.Synced, title: 'Synced'},
    {value: DeploymentStatusTypes.Inactive, title: 'Inactive'},
]);
const selectedStatus = ref([DeploymentStatusTypes.OutOfSync, DeploymentStatusTypes.Synced]);

/** Empty is every health - including none, which is what a Draft has. */
const healthOptions = ref([
    {value: HealthStatusTypes.Degraded, title: 'Degraded'},
    {value: HealthStatusTypes.Missing, title: 'Missing'},
    {value: HealthStatusTypes.Progressing, title: 'Progressing'},
    {value: HealthStatusTypes.Unknown, title: 'Unknown'},
    {value: HealthStatusTypes.Healthy, title: 'Healthy'},
    {value: HealthStatusTypes.Suspended, title: 'Suspended'},
]);
const selectedHealth = ref<string[]>([]);
/** Inside a dialog the list is scoped by its caller: no status filter, and not in the url. */
const isScoped = !!props.filterByWorkspaceId || !!props.filterByIds;
const filtersOnTheList: Record<string, Ref<string[]>> = isScoped ? {} : {status: selectedStatus, health: selectedHealth};

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    // Health sorts by how bad it is; the names would sort alphabetically into nonsense.
    sortable: {'name': 'name', 'status': 'status', 'health': 'health_severity', 'version': 'version', 'last_updated': 'last_updated'},
    defaultSort: {key: 'name', order: 'asc'},
    filters: filtersOnTheList,
    syncWithUrl: !isScoped,
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

watch([selectedStatus, selectedHealth], debounce(() => {
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

    if (props.filterByIds) {
        // An empty whereIn would read as no filter at all.
        api.whereIn('id', props.filterByIds.length ? props.filterByIds : [0]);
    } else if (props.filterByWorkspaceId) {
        api.where('workspace_id', props.filterByWorkspaceId);
    } else {
        api.whereIn('status', selectedStatus.value);
        if (selectedHealth.value.length) {
            api.whereIn('health', selectedHealth.value);
        }
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
        body: `Do you want to delete "${item.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'error',

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

function onShowHistoryBtnClicked(item: Deployment) {
    bus.emit('auditEventList', {
        resourceType: 'Deployment',
        resourceId: item.id!,
        title: item.name,
    });
}

function onOpenItemClicked(item: Deployment) {
    router.push({name: 'DeploymentById', params: {id: item.id}});
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
            color="toolbar"
            dark
            :height="props.filterByWorkspaceId ? undefined : (isPhone ? 104 : 120)"
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
                        :width="isPhone ? undefined : 250"
                        :max-width="isPhone ? undefined : 250"
                    />

                    <list-filters
                        v-if="!props.filterByWorkspaceId"
                        :active-count="(selectedStatus.length ? 1 : 0) + (selectedHealth.length ? 1 : 0)">
                        <v-select
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
                            :width="isPhone ? undefined : 420"
                            :max-width="isPhone ? undefined : 420"
                        />

                        <v-select
                            v-model="selectedHealth"
                            :items="healthOptions"
                            label="Health"
                            density="compact"
                            variant="outlined"
                            multiple
                            item-value="value"
                            item-title="title"
                            hide-details
                            chips
                            closable-chips
                            clearable
                            :width="isPhone ? undefined : 320"
                            :max-width="isPhone ? undefined : 320"
                        />
                    </list-filters>
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
            :show-select="!isPhone"
            item-value="id"
            :headers="shownHeaders"
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
                <name-link @click="onOpenItemClicked(item)">{{ item.name }}</name-link>
                <div class="namespace">{{ item.namespace }}</div>
            </template>
            <template v-slot:item.status="{ item }">
                <deployment-status
                    :deployment="item"/>
            </template>

            <template v-slot:item.health="{ item }">
                <DeploymentHealth :deployment="item"/>
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
                        variant="plain" color="warning"
                        @click="deploy(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom">Deploy</v-tooltip>
                    </v-btn>

                    <!-- Settings are on the page the name opens. Delete belongs in the menu with the rest
                         of what is worth a second thought, not beside Pods. -->
                    <v-menu location="bottom end">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary"
                                aria-label="More"
                                size="small"
                                density="comfortable"
                                icon
                            >
                                <v-icon>fa fa-ellipsis-vertical</v-icon>
                            </v-btn>
                        </template>
                        <v-list density="compact">
                            <v-list-item
                                :disabled="!item.canMigrate"
                                prepend-icon="fa fa-truck-arrow-right"
                                title="Migration Jobs"
                                @click="onShowMigrationJobsBtnClicked(item)"
                            />
                            <v-list-item
                                prepend-icon="fa fa-clock-rotate-left"
                                title="History"
                                @click="onShowHistoryBtnClicked(item)"
                            />
                            <v-divider class="my-1"/>
                            <v-list-item
                                prepend-icon="fa fa-skull"
                                title="Terminate"
                                base-color="error"
                                @click="terminate(item)"
                            />
                            <v-list-item
                                prepend-icon="fa fa-trash"
                                title="Delete"
                                base-color="error"
                                @click="onDeleteItemBtnClicked(item)"
                            />
                        </v-list>
                    </v-menu>
                </div>
            </template>
        </v-data-table-server>

    </div>
</template>

<style scoped>
.namespace {
    font-size: 11px;
    line-height: 1.2;
    color: rgb(var(--v-theme-on-surface));
    opacity: 0.6;
}

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
