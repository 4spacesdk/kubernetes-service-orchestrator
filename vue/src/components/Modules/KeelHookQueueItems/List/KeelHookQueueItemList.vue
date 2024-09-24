<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {KeelHookQueueItem} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import bus from "@/plugins/bus";
import KeelHookQueueItemStatus
    from "@/components/Modules/KeelHookQueueItems/KeelHookQueueItemStatus/KeelHookQueueItemStatus.vue";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";
import debounce from "lodash.debounce";

const props = defineProps<{
    showHeader: boolean;
}>();

const used = ref(false);
const showDialog = ref(false);
const itemCount = ref(0);
const rows = ref<KeelHookQueueItem[]>([]);
const headers = ref<{
    readonly key?: string,
    readonly title?: string | undefined,
    readonly sortable?: boolean | undefined,
    readonly align?: "end" | "center" | "start" | undefined,
}[]>([
    {title: 'Status', key: 'status', sortable: false, align: "center"},
    {title: 'Type', key: 'type', sortable: false},
    {title: 'Deployment', key: 'deployment', sortable: false},
    {title: 'Created', key: 'created', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(false);
const options = ref({});
const wampSubscription1 = ref<WampSubscription>();
const wampSubscription2 = ref<WampSubscription>();

const searchValue = ref('');

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    getItems(false, true);

    wampSubscription1.value = WampService.subscribe(
        Events.KeelHookQueueItem_Created(),
        data => getItems(true, true)
    );
    wampSubscription2.value = WampService.subscribe(
        Events.KeelHookQueueItem_Deleted(),
        data => getItems(true, true)
    );
});

onUnmounted(() => {
    wampSubscription1.value?.unsubscribe();
    wampSubscription2.value?.unsubscribe();
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

function getItems(doItems = true, doCount = false) {
    showDialog.value = true;

    // Get options from DataTable
    const tableOptions: any = options.value;

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.keelHookQueueItems().get();

    if (searchValue.value?.length) {
        api
            .search('status', searchValue.value)
            .search('data', searchValue.value);
    }

    if (doItems) {
        api
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderDesc('id')
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

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onShowPayloadBtnClicked(row: KeelHookQueueItem) {
    bus.emit('json', {
        title: 'Keel Hook Queue Item: Payload',
        body: JSON.parse(row.data ?? '{}'),
    });
}

function onRerunBtnClicked(row: KeelHookQueueItem) {
    Api.keelHookQueueItems().rerunPostById(row.id!)
        .save(null, () => {
            getItems(true, true);
        });
}

function onShowLogsBtnClicked(row: KeelHookQueueItem) {
    const code = row?.log?.trim().split('\n').map(k => {
        if(k.length)
            return `<span>${k}</span>`;
    }).join('');
    bus.emit('info', {
        title: 'Keel Hook Queue Item: Log',
        body: `<code class="line-numbers">${code}</code>`
    })
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
            <v-toolbar-title>Keel Hook Queue Items</v-toolbar-title>

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
                <KeelHookQueueItemStatus
                    :keel-hook-queue-item="item"/>
            </template>

            <template v-slot:item.type="{ item }">
                {{ item.payload.name }}
            </template>

            <template v-slot:item.deployment="{ item }">
                <v-chip>{{ item.payload.metadata.name }}.{{ item.payload.metadata.namespace }}</v-chip>
            </template>

            <template v-slot:item.created="{ item }">
                <DateView :date-string="item.created"/>
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onRerunBtnClicked(item)">
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom">Rerun</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowPayloadBtnClicked(item)">
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Payload</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowLogsBtnClicked(item)">
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Log</v-tooltip>
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
