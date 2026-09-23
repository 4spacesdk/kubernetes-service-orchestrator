<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import ListFilters from "@/components/Modules/Common/List/ListFilters.vue";
import { computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import { WorkspaceTemplate, Workspace } from "@/core/services/Deploy/models";
import WorkspaceEditButton from "@/components/Modules/Workspaces/EditButton/WorkspaceEditButton.vue";
import WorkspaceDeploymentStatus from "@/components/Modules/Workspaces/WorkspaceDeploymentStatus/WorkspaceDeploymentStatus.vue";
import debounce from "lodash.debounce";
import WorkspaceDeploymentDomains from "@/components/Modules/Workspaces/WorkspaceDeploymentDomains/WorkspaceDeploymentDomains.vue";
import { WorkspaceStatusTypes, HealthStatusTypes } from "@/constants";
import { useWorkspaceActions } from "@/composables/useWorkspaceActions";
import WorkspaceHealth from "@/components/Modules/Workspaces/WorkspaceHealth/WorkspaceHealth.vue";
import { useRouter } from "vue-router";
import { useDisplay } from "vuetify";

interface Row {
    workspace: Workspace;
    isLoadingDeleteBtn?: boolean;
}

const router = useRouter();

/** A phone: the search takes the width, and the filters go behind a button. */
const { xs: isPhone } = useDisplay();

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    // The namespace is under the name rather than in a column of its own: it is rarely what
    // anybody is looking for, and the row is wide enough as it is. It is still searched.
    { title: "Name", key: "workspace.name_readable", sortable: true },
    { title: "Status", key: "status", sortable: true },
    { title: "Health", key: "health", sortable: true },
    { title: "Url", key: "url", sortable: false },
    { title: "", key: "actions", sortable: false },
]);
const isLoading = ref(true);
const options = ref({});

const showCreateMenu = ref(false);
const workspaceTemplates = ref<WorkspaceTemplate[]>([]);
const showWorkspaceTemplatesWarning = ref(true);

const statusOptions = ref([
    {
        value: WorkspaceStatusTypes.Draft,
        title: "Draft",
    },
    {
        value: WorkspaceStatusTypes.OutOfSync,
        title: "Out of sync",
    },
    {
        value: WorkspaceStatusTypes.Synced,
        title: "Synced",
    },
    {
        value: WorkspaceStatusTypes.Inactive,
        title: "Inactive",
    },
    {
        value: WorkspaceStatusTypes.Paused,
        title: "Paused",
    },
]);
const selectedStatus = ref([WorkspaceStatusTypes.OutOfSync, WorkspaceStatusTypes.Synced]);

/** Empty is every health - including none, which is what a workspace with only drafts has. */
const healthOptions = ref([
    { value: HealthStatusTypes.Degraded, title: "Degraded" },
    { value: HealthStatusTypes.Missing, title: "Missing" },
    { value: HealthStatusTypes.Progressing, title: "Progressing" },
    { value: HealthStatusTypes.Unknown, title: "Unknown" },
    { value: HealthStatusTypes.Healthy, title: "Healthy" },
    { value: HealthStatusTypes.Suspended, title: "Suspended" },
]);
const selectedHealth = ref<string[]>([]);
const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    // Health sorts by how bad it is; the names would sort alphabetically into nonsense.
    sortable: {"workspace.name_readable": "name_readable", "status": "status", "health": "health_severity"},
    defaultSort: {key: "workspace.name_readable", order: "asc"},
    itemsPerPage: -1,
    filters: { status: selectedStatus, health: selectedHealth },
});

const {
    rbacDeveloper,
    rbacWorkspaceCreate,
    rbacWorkspaceUpdate,
    deploy,
    terminate,
    pause,
    resume,
    remove,
    showMigrationJobs,
    showHistory,
    showLogs,
} = useWorkspaceActions();

onMounted(() => {
    bus.on("workspaceSaved", onItemSaved);

    getItems(false, true);

    Api.workspaceTemplates()
        .get()
        .find((items) => {
            workspaceTemplates.value = items;
            showWorkspaceTemplatesWarning.value = workspaceTemplates.value.length === 0;
        });
});

onUnmounted(() => {
    bus.off("workspaceSaved", onItemSaved);
});

watch(
    searchValue,
    debounce(() => {
        getItems(true, true);
    }, 500)
);
watch(
    [selectedStatus, selectedHealth],
    debounce(() => {
        getItems(true, true);
    }, 500)
);

function onItemSaved() {
    getItems(true, true);
}

function getItems(doItems = true, doCount = false) {

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.workspaces().get();

    if (searchValue.value?.length) {
        api.search("aliases", searchValue.value)
            .search("name_readable", searchValue.value)
            .search("name_system", searchValue.value)
            .search("namespace", searchValue.value)
            .search("subdomain", searchValue.value);
    }

    api.whereIn("status", selectedStatus.value);
    if (selectedHealth.value.length) {
        api.whereIn("health", selectedHealth.value);
    }

    if (doItems) {
        applyPaging(api);
        applyOrdering(api);
        api.include("deployment")
            .include("domain")
            .find((items) => {
                rows.value = items.map((item) => {
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
        api.count((count) => {
            itemCount.value = count;
        });
    }
}

// <editor-fold desc="View function">

function onCreateItemBtnClicked(type: WorkspaceTemplate) {
    bus.emit("workspaceCreate", {
        workspaceTemplate: type,
    });
}

function onOpenItemClicked(item: Workspace) {
    router.push({ name: "WorkspaceById", params: { id: item.id } });
}

function onWorkspaceTemplatesShortcutClicked() {
    router.push({ name: "WorkspaceTemplates" }).catch((e: any) => {});
}

// </editor-fold>
</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar density="compact" flat color="toolbar" dark :height="isPhone ? 104 : 120">
            <div class="d-flex flex-column w-100 py-2 px-4 gap-1">
                <div class="d-flex">
                    <v-toolbar-title class="my-auto">Workspaces</v-toolbar-title>

                    <v-spacer></v-spacer>

                    <v-menu
                        v-if="rbacWorkspaceCreate"
                        v-model="showCreateMenu"
                        :close-on-content-click="false"
                        left
                        min-width="250"
                        offset-y
                    >
                        <template v-slot:activator="{ props }">
                            <v-btn data-shortcut="create" v-bind="props" small prepend-icon="fa fa-plus" style="margin-right: -16px"> Create </v-btn>
                        </template>

                        <v-list v-if="showWorkspaceTemplatesWarning" class="list-items">
                            <v-list-item dense>
                                <v-list-item-title>
                                    <span class="font-italic">No Workspace Template found.</span>
                                </v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="onWorkspaceTemplatesShortcutClicked">
                                <v-list-item-title>
                                    <v-icon size="small" class="my-auto">fa fa-circle-right</v-icon>
                                    <span class="ml-2">Go to Workspace Templates</span>
                                </v-list-item-title>
                            </v-list-item>
                        </v-list>

                        <v-list v-else class="list-items">
                            <v-list-item v-for="(type, i) in workspaceTemplates" :key="i" dense @click="onCreateItemBtnClicked(type)">
                                <v-list-item-title>
                                    <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                                    <span class="ml-2">{{ type.name }}</span>
                                </v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                </div>

                <div class="d-flex gap-1">
                    <v-text-field data-shortcut="search"
                        v-model="searchValue"
                        variant="outlined"
                        hide-details
                        placeholder="Search"
                        clearable
                        :width="isPhone ? undefined : 250"
                        :max-width="isPhone ? undefined : 250"
                    />

                    <list-filters :active-count="(selectedStatus.length ? 1 : 0) + (selectedHealth.length ? 1 : 0)">
                        <v-select
                            v-model="selectedStatus"
                            :items="statusOptions"
                            label="Status"
                            variant="outlined"
                            multiple
                            item-value="value"
                            item-title="title"
                            hide-details
                            chips
                            closable-chips
                            clearable
                        />

                        <v-select
                            v-model="selectedHealth"
                            :items="healthOptions"
                            label="Health"
                            variant="outlined"
                            multiple
                            item-value="value"
                            item-title="title"
                            hide-details
                            chips
                            closable-chips
                            clearable
                        />
                    </list-filters>
                </div>
            </div>
        </v-toolbar>

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :loading="isLoading"
            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            v-model:sort-by="sortBy"
            class="table"
            density="compact"
            @update:options="
                options = $event;
                getItems();
            "
        >
            <template v-slot:item.status="{ item }">
                <WorkspaceDeploymentStatus :workspace="item.workspace" />
            </template>

            <template v-slot:item.health="{ item }">
                <WorkspaceHealth :workspace="item.workspace" @click="onOpenItemClicked(item.workspace)" />
            </template>

            <template v-slot:item.url="{ item }">
                <workspace-deployment-domains :workspace="item.workspace" />
            </template>

            <template v-slot:item.workspace.name_readable="{ item }">
                <name-link @click="onOpenItemClicked(item.workspace)">{{ item.workspace.name_readable }}</name-link>
                <div class="namespace">{{ item.workspace.namespace }}</div>
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <!-- The name opens the workspace's page, and logs and migration jobs are in
                         the menu: the two most used, Settings and Deploy, are the ones on the row. -->
                    <v-menu v-if="rbacWorkspaceUpdate" min-width="250">
                        <template v-slot:activator="{ props }">
                            <v-btn v-bind="props" variant="plain" color="primary" size="small" density="comfortable" icon>
                                <v-icon>fa fa-cog</v-icon>
                                <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                            </v-btn>
                        </template>
                        <WorkspaceEditButton :workspace="item.workspace" />
                    </v-menu>

                    <v-btn
                        v-if="rbacWorkspaceCreate"
                        variant="plain"
                        color="warning"
                        @click="deploy(item.workspace)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom">Deploy</v-tooltip>
                    </v-btn>

                    <!-- Terminate and Delete stay out of reach of a slip from Deploy. -->
                    <v-menu v-if="rbacDeveloper || rbacWorkspaceUpdate" location="bottom end">
                        <template v-slot:activator="{ props }">
                            <v-btn v-bind="props" variant="plain" color="primary"  aria-label="More" :loading="item.isLoadingDeleteBtn" size="small" density="comfortable" icon>
                                <v-icon>fa fa-ellipsis-vertical</v-icon>
                            </v-btn>
                        </template>
                        <v-list density="compact">
                            <v-list-item
                                v-if="rbacDeveloper"
                                prepend-icon="fa fa-rectangle-list"
                                title="Kubernetes Logs"
                                @click="showLogs(item.workspace)"
                            />
                            <v-list-item
                                v-if="rbacDeveloper"
                                prepend-icon="fa fa-truck-arrow-right"
                                title="Migration Jobs"
                                @click="showMigrationJobs(item.workspace)"
                            />
                            <v-list-item
                                v-if="rbacDeveloper"
                                prepend-icon="fa fa-clock-rotate-left"
                                title="History"
                                @click="showHistory(item.workspace)"
                            />
                            <v-divider v-if="rbacDeveloper || rbacWorkspaceUpdate" class="my-1" />
                            <v-list-item
                                v-if="rbacDeveloper && !item.workspace.is_paused"
                                prepend-icon="fa fa-pause"
                                title="Pause"
                                base-color="error"
                                @click="pause(item.workspace)"
                            />
                            <v-list-item
                                v-if="rbacDeveloper && item.workspace.is_paused"
                                prepend-icon="fa fa-play"
                                title="Resume"
                                @click="resume(item.workspace)"
                            />
                            <v-list-item
                                v-if="rbacDeveloper"
                                prepend-icon="fa fa-skull"
                                title="Terminate"
                                base-color="error"
                                @click="terminate(item.workspace)"
                            />
                            <v-list-item
                                v-if="rbacWorkspaceUpdate"
                                prepend-icon="fa fa-trash"
                                title="Delete"
                                base-color="error"
                                @click="remove(item.workspace, { onBusy: busy => (item.isLoadingDeleteBtn = busy) })"
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

.table > *,
.table {
    background: transparent;
}
</style>
