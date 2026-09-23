<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, MigrationJob} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import bus from "@/plugins/bus";
import MigrationJobStatus from "@/components/Modules/MigrationJobs/MigrationJobStatus/MigrationJobStatus.vue";
import {PushSubscription} from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import {Events} from "@/services/Push/Events";
import debounce from "lodash.debounce";

interface Row {
    item: MigrationJob;
    isLoadingRerun: boolean;
}

const props = defineProps<{
    filterByDeploymentId?: number;
    filterByWorkspaceId?: number;

    showHeader: boolean;
}>();

const used = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref<{
    readonly key?: string,
    readonly title?: string | undefined,
    readonly sortable?: boolean | undefined,
    readonly align?: "end" | "center" | "start" | undefined,
}[]>([
    {title: 'Status', key: 'status', sortable: true, align: "center"},
    {title: 'Workspace', key: 'item.deployment.workspace.name', sortable: false},
    {title: 'Deployment', key: 'deployment', sortable: true},
    {title: 'Created', key: 'created', sortable: true},
    {title: 'Started', key: 'started', sortable: true},
    {title: 'Ended', key: 'ended', sortable: true},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(false);
const options = ref({});
const pushSubscription = ref<PushSubscription>();

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"created": "id", "status": "status", "deployment": "deployment.name", "started": "started", "ended": "ended"},
    defaultSort: {key: "created", order: "desc"},
    syncWithUrl: !props.filterByDeploymentId && !props.filterByWorkspaceId,
});

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    getItems(false, true);

    pushSubscription.value = PushService.subscribe(
        Events.MigrationJob_Created(),
        data => getItems(true, true)
    );
});

onUnmounted(() => {
    pushSubscription.value?.unsubscribe();
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

function getItems(doItems = true, doCount = false) {

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.migrationJobs().get();
    if (props.filterByWorkspaceId) {
        api.where('deployment.workspace_id', props.filterByWorkspaceId);
    }
    if (props.filterByDeploymentId) {
        api.where('deployment_id', props.filterByDeploymentId);
    }

    if (searchValue.value?.length) {
        api
            .search('status', searchValue.value)
            .search('deployment.name', searchValue.value)
            .search('deployment.namespace', searchValue.value)
            .search('deployment.workspace.name_readable', searchValue.value);
    }

    if (doItems) {
        applyPaging(api);
        applyOrdering(api);
        api
            .find(items => {
                rows.value = items.map(item => {
                    return {
                        item: item,
                        isLoadingRerun: false,
                    }
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

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onShowKubernetesLogsBtnClicked(row: MigrationJob) {
    bus.emit('migrationJobLogs', {
        migrationJob: row
    });
}

function onShowLogsBtnClicked(row: MigrationJob) {
    bus.emit('info', {
        title: 'Migration Job: Log',
        body: row?.log?.trim() ?? '',
        monospace: true,
    })
}

function onRerunBtnClicked(row: Row) {
    row.isLoadingRerun = true;
    Api.migrationJobs().rerunPutById(row.item.id!)
        .save(null, () => {
            row.isLoadingRerun = false;
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
        >
            <v-toolbar-title>Migration Jobs</v-toolbar-title>

            <v-text-field data-shortcut="search"
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
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
            @update:options="options = $event; getItems()">

            <template v-slot:item.status="{ item }">
                <MigrationJobStatus
                    :migration-job="item.item"/>
            </template>

            <template v-slot:item.deployment="{ item }">
                <v-chip
                    v-if="item.item.deployment"
                    style="max-width: 200px"
                >
                    <span class="text-truncate">
                        {{ item.item.deployment.name }}.{{ item.item.deployment.namespace }}
                    </span>
                    <v-tooltip activator="parent" location="bottom">{{ item.item.deployment.name }}.{{ item.item.deployment.namespace }}</v-tooltip>
                </v-chip>
            </template>

            <template v-slot:item.created="{ item }">
                <DateView :date-string="item.item.created"/>
            </template>

            <template v-slot:item.started="{ item }">
                <DateView
                    v-if="item.item.started"
                    :date-string="item.item.started"/>
            </template>

            <template v-slot:item.ended="{ item }">
                <DateView
                    v-if="item.item.ended"
                    :date-string="item.item.ended"/>
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <v-btn
                        variant="plain" color="primary" 
                        @click="onShowKubernetesLogsBtnClicked(item.item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Kubernetes Log</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" 
                        @click="onShowLogsBtnClicked(item.item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Job Log</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="warning" 
                        @click="onRerunBtnClicked(item)"
                        :loading="item.isLoadingRerun"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom">Rerun</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>

    </div>
</template>

<style scoped>
.icon-wrapper {
    width: 32px;
    vertical-align: center;
    justify-content: center;
    align-items: center;
}
</style>
