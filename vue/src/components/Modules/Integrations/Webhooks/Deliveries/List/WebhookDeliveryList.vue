<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import {WebhookDelivery} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import DateView from "@/components/Modules/Common/DateView.vue";
import bus from "@/plugins/bus";

const props = defineProps<{
    filterByWebhookId?: number;

    showHeader: boolean;
}>();

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: WebhookDelivery): void
}>();

interface Row {
    item: WebhookDelivery,
    isRunning: boolean,
}

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Date', key: 'created', sortable: false},
    {title: 'Url', key: 'item.url', sortable: false},
    {title: 'Response code', key: 'item.response_code', sortable: false},
    {title: 'Time', key: 'response_time', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

onMounted(() => {
    getItems(false, true);
});

onUnmounted(() => {

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
    const api = Api.webhooks().deliveriesGetGetByWebhookId(props.filterByWebhookId!);

    if (searchValue.value?.length) {
        api
            .search('payload', searchValue.value)
            .search('response_body', searchValue.value);
    }

    if (doItems) {
        api
            .orderDesc('id')
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .find((items: WebhookDelivery[]) => {
                rows.value = items.map(item => {
                    return {
                        item: item,
                        isRunning: false,
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

// <editor-fold desc="View functions">

function onRetryItemBtnClicked(item: Row) {
    item.isRunning = true;
    Api.webhooks()
        .deliveriesRetryPutByWebhookIdByWebhookDeliveryId(item.item.webhook_id!, item.item.id!)
        .save(null, () => {
            item.isRunning = false;
            getItems(true, true);
        });
}

function onRequestPayloadBtnClicked(item: Row) {
    bus.emit('info', {
        title: 'Request payload',
        body: item.item.payload ?? ''
    });
}

function onResponseHeadersBtnClicked(item: Row) {
    bus.emit('info', {
        title: 'Response headers',
        body: item.item.response_headers ?? ''
    });
}

function onResponseBodyBtnClicked(item: Row) {
    bus.emit('info', {
        title: 'Response body',
        body: item.item.response_body ?? ''
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
            <v-toolbar-title>Webhook Deliveries</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />
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
            <template v-slot:item.created="{ item }">
                <DateView :date-string="item.raw.item.created"/>
            </template>
            <template v-slot:item.response_time="{ item }">
                <span>{{ item.raw.item.response_time }}ms</span>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onRequestPayloadBtnClicked(item.raw)"
                    >
                        <v-icon>fa fa-eye</v-icon>
                        <v-tooltip activator="parent" location="bottom">Request payload</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="secondary" size="small" icon
                        @click="onResponseHeadersBtnClicked(item.raw)"
                    >
                        <v-icon>fa fa-eye</v-icon>
                        <v-tooltip activator="parent" location="bottom">Response headers</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="secondary" size="small" icon
                        @click="onResponseBodyBtnClicked(item.raw)"
                    >
                        <v-icon>fa fa-eye</v-icon>
                        <v-tooltip activator="parent" location="bottom">Response body</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onRetryItemBtnClicked(item.raw)"
                        :loading="item.raw.isRunning"
                    >
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom">Redelivery</v-tooltip>
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
