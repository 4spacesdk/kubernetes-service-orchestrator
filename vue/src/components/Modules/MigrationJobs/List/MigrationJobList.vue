<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, MigrationJob} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import bus from "@/plugins/bus";
import MigrationJobStatus from "@/components/Modules/MigrationJobs/MigrationJobStatus/MigrationJobStatus.vue";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";
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
const headers = ref([
    {title: 'Status', key: 'status', sortable: false, align: "center"},
    {title: 'Workspace', key: 'item.deployment.workspace.name', sortable: false},
    {title: 'Deployment', key: 'deployment', sortable: false},
    {title: 'Created', key: 'created', sortable: false},
    {title: 'Started', key: 'started', sortable: false},
    {title: 'Ended', key: 'ended', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(false);
const options = ref({});
const wampSubscription = ref<WampSubscription>();

const searchValue = ref('');

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    getItems(false, true);

    wampSubscription.value = WampService.subscribe(
        Events.MigrationJob_Created(),
        data => getItems(true, true)
    );
});

onUnmounted(() => {
    wampSubscription.value?.unsubscribe();
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

function getItems(doItems = true, doCount = false) {
    // Get options from DataTable
    const tableOptions: any = options.value;

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
        api
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderDesc('id')
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
    const code = row?.log
        ?.trim()
        ?.split('\n')
        ?.map(line => `<span>${line}</span>`)
        ?.join('') ?? '';

    bus.emit('info', {
        title: 'Migration Job: Log',
        body: `<code class="line-numbers">${code}</code>`
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
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>Migration Jobs</v-toolbar-title>

            <v-text-field
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
            :items-per-page="50"
            class="table"
            density="compact"
            @update:options="options = $event; getItems()">

            <template v-slot:item.status="{ item }">
                <MigrationJobStatus
                    :migration-job="item.raw.item"/>
            </template>

            <template v-slot:item.deployment="{ item }">
                <v-chip
                    v-if="item.raw.item.deployment"
                    style="max-width: 200px"
                >
                    <span class="text-truncate">
                        {{ item.raw.item.deployment.name }}.{{ item.raw.item.deployment.namespace }}
                    </span>
                    <v-tooltip activator="parent" location="bottom">{{ item.raw.item.deployment.name }}.{{ item.raw.item.deployment.namespace }}</v-tooltip>
                </v-chip>
            </template>

            <template v-slot:item.created="{ item }">
                <DateView :date-string="item.raw.item.created"/>
            </template>

            <template v-slot:item.started="{ item }">
                <DateView
                    v-if="item.raw.item.started"
                    :date-string="item.raw.item.started"/>
            </template>

            <template v-slot:item.ended="{ item }">
                <DateView
                    v-if="item.raw.item.ended"
                    :date-string="item.raw.item.ended"/>
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowKubernetesLogsBtnClicked(item.raw.item)">
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Kubernetes Log</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowLogsBtnClicked(item.raw.item)">
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Job Log</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="warning" size="small" icon
                        @click="onRerunBtnClicked(item.raw)"
                        :loading="item.raw.isLoadingRerun"
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
