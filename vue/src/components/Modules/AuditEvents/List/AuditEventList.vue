<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import {onMounted, ref, watch, type Ref} from 'vue'
import {AuditEvent} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import debounce from "lodash.debounce";

interface Row {
    item: AuditEvent;
    changes: {field: string, before: any, after: any}[];
    details: {key: string, value: any}[];
}

const props = defineProps<{
    filterByResourceType?: string;
    filterByResourceId?: number;
    filterByUserId?: number;

    showHeader: boolean;
}>();

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const expanded = ref<string[]>([]);
const headers = ref<{
    readonly key?: string,
    readonly title?: string | undefined,
    readonly sortable?: boolean | undefined,
    readonly align?: "end" | "center" | "start" | undefined,
}[]>([
    {title: 'When', key: 'created', sortable: true},
    {title: 'Who', key: 'who', sortable: false},
    {title: 'Action', key: 'action', sortable: true},
    {title: 'What', key: 'resource', sortable: true},
    {title: '', key: 'data-table-expand', sortable: false, align: "end"},
]);
const isLoading = ref(false);

/** Scoped by its caller - a thing's history - it has no filters of its own, and no url. */
const isScoped = !!props.filterByResourceType || !!props.filterByUserId;

const selectedUsers = ref<string[]>([]);
const selectedSources = ref<string[]>([]);
const selectedActions = ref<string[]>([]);
const selectedTypes = ref<string[]>([]);
const fromDate = ref<string[]>([]);
const toDate = ref<string[]>([]);
const filtersOnTheList: Record<string, Ref<string[]>> = isScoped ? {} : {
    user: selectedUsers,
    source: selectedSources,
    action: selectedActions,
    type: selectedTypes,
    from: fromDate,
    to: toDate,
};

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"created": "id", "action": "action", "resource": "resource_type"},
    defaultSort: {key: "created", order: "desc"},
    filters: filtersOnTheList,
    syncWithUrl: !isScoped,
});

const userOptions = ref<{value: string, title: string}[]>([]);
const userNames = ref<Record<string, string>>({});
const sourceOptions = [
    {value: 'ui', title: 'The app'},
    {value: 'api', title: 'API client'},
    {value: 'web', title: 'Sign-in pages and webhooks'},
    {value: 'cron', title: 'kso (cron)'},
    {value: 'queue', title: 'kso (queue)'},
    {value: 'cli', title: 'kso (command)'},
];
/** Every action kso records - see Audit::Record in the backend. */
const actionOptions = [
    'created', 'updated', 'deleted', 'relation_added', 'relation_removed',
    'workspace.deploy', 'workspace.terminate', 'workspace.pause', 'workspace.resume',
    'deployment.deploy', 'deployment.terminate',
    'deployment.deploy_step', 'deployment.terminate_step', 'deployment.run_cron_job',
    'gateway.deploy', 'gateway.terminate',
    'domain.apply_certificate', 'domain.apply_istio_gateway', 'domain.terminate_istio_gateway',
    'pod.exec', 'container_image.scan', 'container_registry.setup_events',
    'migration_job.rerun', 'auto_update.approve', 'webhook.retry_delivery',
    'user.sign_in', 'user.sign_out',
].map(value => ({value, title: actionTitle(value)}));
/** The ones people look for; any other can be typed. */
const typeOptions = [
    'Workspace', 'Deployment', 'DeploymentSpecification', 'WorkspaceTemplate', 'Domain', 'Gateway',
    'ContainerImage', 'ContainerRegistry', 'DatabaseService', 'EmailService', 'EnvironmentVariable',
    'Webhook', 'User', 'RbacRole', 'OAuthClient', 'GithubIntegration', 'PodioIntegration',
    'CronJob', 'System', 'Pod',
];

/** What kso did by itself, when nobody was signed in. */
const SystemSources: Record<string, string> = {
    cron: 'kso (cron)',
    queue: 'kso (queue)',
    cli: 'kso (command)',
    web: 'Anonymous',
};

// <editor-fold desc="Functions">

onMounted(() => {
    getItems(false, true);

    // The trail has only `user_id` - see AuditEventModel - so the names come from here, for
    // the rows and for the filter.
    Api.users().get().orderAsc('first_name').find(users => {
        userOptions.value = users.map(user => ({value: String(user.id), title: user.name}));
        userNames.value = Object.fromEntries(users.map(user => [String(user.id), user.name]));
    });
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

watch([selectedUsers, selectedSources, selectedActions, selectedTypes, fromDate, toDate], debounce(() => {
    getItems(true, true);
}, 500), {deep: true});

function getItems(doItems = true, doCount = false) {
    isLoading.value = true;

    const api = Api.auditEvents().get();
    if (props.filterByResourceType) {
        api.where('resource_type', props.filterByResourceType);
    }
    if (props.filterByResourceId) {
        api.where('resource_id', props.filterByResourceId);
    }
    if (props.filterByUserId) {
        api.where('user_id', props.filterByUserId);
    }
    if (selectedUsers.value.length) {
        api.whereIn('user_id', selectedUsers.value.map(Number));
    }
    if (selectedSources.value.length) {
        api.whereIn('source', selectedSources.value);
    }
    if (selectedActions.value.length) {
        api.whereIn('action', selectedActions.value);
    }
    if (selectedTypes.value.length) {
        api.whereIn('resource_type', selectedTypes.value);
    }
    // `2026-09-22T14:30` from the input, to the minute - `to` takes in the whole minute.
    if (fromDate.value[0]) {
        api.whereGreaterThanOrEqual('created', `${fromDate.value[0].replace('T', ' ')}:00`);
    }
    if (toDate.value[0]) {
        api.whereLessThanOrEqual('created', `${toDate.value[0].replace('T', ' ')}:59`);
    }

    if (searchValue.value?.length) {
        api
            .search('action', searchValue.value)
            .search('resource_type', searchValue.value)
            .search('resource_name', searchValue.value);
    }

    if (doItems) {
        applyPaging(api);
        applyOrdering(api);
        api.find(items => {
            rows.value = items.map(item => toRow(item));
            isLoading.value = false;
        });
    }

    if (doCount) {
        api.count(count => {
            itemCount.value = count;
        });
    }
}

function toRow(item: AuditEvent): Row {
    const {changes, ...rest} = item.parsedDetails;
    return {
        item: item,
        changes: Object.entries(changes ?? {}).map(([field, [before, after]]) => ({field, before, after})),
        details: Object.entries(rest).map(([key, value]) => ({key, value})),
    };
}

function who(item: AuditEvent): string {
    if (item.user_id && userNames.value[String(item.user_id)]) {
        return userNames.value[String(item.user_id)];
    }
    if (item.user_id) {
        return `User #${item.user_id}`;
    }
    return SystemSources[item.source ?? ''] ?? item.source ?? '';
}

/** `workspace.deploy` as Workspace: deploy - for the filter, where the resource is not beside it. */
function actionTitle(action: string): string {
    const [first, second] = action.includes('.') ? action.split('.') : ['', action];
    const words = (value: string) => value.replace(/_/g, ' ');
    const named = words(second);
    return first ? `${words(first).replace(/^./, c => c.toUpperCase())}: ${named}` : named.replace(/^./, c => c.toUpperCase());
}

/** `created` as Created, `workspace.deploy` as Deploy - the resource is its own column. */
function actionLabel(action?: string): string {
    const name = (action ?? '').split('.').pop() ?? '';
    const words = name.replace(/_/g, ' ');
    return words.charAt(0).toUpperCase() + words.slice(1);
}

function actionColor(action?: string): string {
    switch (action) {
        case 'created':
        case 'relation_added':
            return 'success';
        case 'deleted':
        case 'relation_removed':
            return 'error';
        case 'updated':
            return 'info';
        default:
            return 'purple';
    }
}

function show(value: any): string {
    if (value === null || value === undefined) {
        return '—';
    }
    return typeof value === 'string' ? value : JSON.stringify(value);
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
            <v-toolbar-title>Audit Trail</v-toolbar-title>

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

        <div
            v-if="props.showHeader && !isScoped"
            class="filters d-flex flex-wrap ga-2 px-4 py-2">
            <v-autocomplete
                v-model="selectedUsers"
                :items="userOptions"
                label="Who"
                density="compact"
                variant="outlined"
                multiple
                clearable
                hide-details
                style="flex: 1 1 200px; min-width: 200px; max-width: 280px"
            >
                <template v-slot:selection="{ internalItem: item, index }">
                    <span v-if="index === 0" class="text-truncate">{{ item.title }}</span>
                    <span v-if="index === 1" class="text-medium-emphasis text-body-small ml-1 text-no-wrap">+{{ selectedUsers.length - 1 }}</span>
                </template>
            </v-autocomplete>
            <v-select
                v-model="selectedSources"
                :items="sourceOptions"
                label="Source"
                density="compact"
                variant="outlined"
                multiple
                clearable
                hide-details
                style="flex: 1 1 160px; min-width: 160px; max-width: 280px"
            >
                <template v-slot:selection="{ internalItem: item, index }">
                    <span v-if="index === 0" class="text-truncate">{{ item.title }}</span>
                    <span v-if="index === 1" class="text-medium-emphasis text-body-small ml-1 text-no-wrap">+{{ selectedSources.length - 1 }}</span>
                </template>
            </v-select>
            <v-autocomplete
                v-model="selectedActions"
                :items="actionOptions"
                label="Action"
                density="compact"
                variant="outlined"
                multiple
                clearable
                hide-details
                style="flex: 1 1 200px; min-width: 200px; max-width: 320px"
            >
                <template v-slot:selection="{ internalItem: item, index }">
                    <span v-if="index === 0" class="text-truncate">{{ item.title }}</span>
                    <span v-if="index === 1" class="text-medium-emphasis text-body-small ml-1 text-no-wrap">+{{ selectedActions.length - 1 }}</span>
                </template>
            </v-autocomplete>
            <v-combobox
                v-model="selectedTypes"
                :items="typeOptions"
                label="What"
                density="compact"
                variant="outlined"
                multiple
                clearable
                hide-details
                style="flex: 1 1 200px; min-width: 200px; max-width: 320px"
            >
                <template v-slot:selection="{ internalItem: item, index }">
                    <span v-if="index === 0" class="text-truncate">{{ item.title }}</span>
                    <span v-if="index === 1" class="text-medium-emphasis text-body-small ml-1 text-no-wrap">+{{ selectedTypes.length - 1 }}</span>
                </template>
            </v-combobox>
            <v-text-field
                :model-value="fromDate[0] ?? ''"
                @update:model-value="fromDate = $event ? [$event] : []"
                type="datetime-local"
                label="From"
                density="compact"
                variant="outlined"
                hide-details
                style="flex: 0 0 220px"
            />
            <v-text-field
                :model-value="toDate[0] ?? ''"
                @update:model-value="toDate = $event ? [$event] : []"
                type="datetime-local"
                label="To"
                density="compact"
                variant="outlined"
                hide-details
                style="flex: 0 0 220px"
            />
        </div>

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :loading="isLoading"
            item-value="item.id"
            v-model:expanded="expanded"
            show-expand
            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            v-model:sort-by="sortBy"
            class="table"
            density="compact"
            @update:options="getItems()">

            <template v-slot:item.created="{ item }">
                <DateView :date-string="item.item.created"/>
            </template>

            <template v-slot:item.who="{ item }">
                <span>{{ who(item.item) }}</span>
                <span
                    v-if="item.item.user_id && item.item.source"
                    class="text-medium-emphasis text-body-small ml-1">
                    {{ item.item.source }}
                </span>
                <v-tooltip
                    v-if="item.item.ip_address"
                    activator="parent"
                    location="bottom">
                    {{ item.item.ip_address }}<span v-if="item.item.client_id"> · {{ item.item.client_id }}</span>
                </v-tooltip>
            </template>

            <template v-slot:item.action="{ item }">
                <v-chip
                    size="small"
                    :color="actionColor(item.item.action)"
                    variant="tonal">
                    {{ actionLabel(item.item.action) }}
                </v-chip>
            </template>

            <template v-slot:item.resource="{ item }">
                <span class="text-medium-emphasis">{{ item.item.resource_type }}</span>
                {{ item.item.resource_name ?? (item.item.resource_id ? `#${item.item.resource_id}` : '') }}
                <span
                    v-if="item.changes.length"
                    class="text-medium-emphasis text-body-small ml-1">
                    {{ item.changes.map(change => change.field).join(', ') }}
                </span>
            </template>

            <template v-slot:item.data-table-expand="{ item, internalItem, isExpanded, toggleExpand }">
                <v-btn
                    v-if="item.changes.length || item.details.length"
                    variant="plain"
                    size="small"
                    density="comfortable"
                    icon
                    @click="toggleExpand(internalItem)">
                    <v-icon>{{ isExpanded(internalItem) ? 'fa fa-chevron-up' : 'fa fa-chevron-down' }}</v-icon>
                </v-btn>
            </template>

            <template v-slot:expanded-row="{ columns, item }">
                <tr>
                    <td :colspan="columns.length" class="py-2">
                        <v-table
                            v-if="item.changes.length"
                            density="compact"
                            class="changes">
                            <thead>
                            <tr>
                                <th>Field</th>
                                <th>Before</th>
                                <th>After</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="change in item.changes" :key="change.field">
                                <td>{{ change.field }}</td>
                                <td class="value">{{ show(change.before) }}</td>
                                <td class="value">{{ show(change.after) }}</td>
                            </tr>
                            </tbody>
                        </v-table>
                        <v-table
                            v-if="item.details.length"
                            density="compact"
                            class="changes">
                            <tbody>
                            <tr v-for="detail in item.details" :key="detail.key">
                                <td>{{ detail.key }}</td>
                                <td class="value">{{ show(detail.value) }}</td>
                            </tr>
                            </tbody>
                        </v-table>
                    </td>
                </tr>
            </template>
        </v-data-table-server>

    </div>
</template>

<style scoped>
/* The browser's own date input is two pixels taller than the selects beside it. */
.filters :deep(input[type="datetime-local"]) {
    height: 40px;
}

.changes {
    background: transparent;
}

.changes .value {
    font-family: monospace;
    white-space: pre-wrap;
    word-break: break-all;
    max-width: 40vw;
}
</style>
