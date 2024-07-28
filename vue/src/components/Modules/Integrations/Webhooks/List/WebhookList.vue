<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {Webhook} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: Webhook): void
}>();

const itemCount = ref(0);
const rows = ref<Webhook[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Type', key: 'type', sortable: false},
    {title: 'Url', key: 'url', sortable: false},
    {title: 'Method', key: 'http_method', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

onMounted(() => {
    bus.on('webhookSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('webhookSaved', onItemSaved);
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
    const api = Api.webhooks().get();

    if (searchValue.value?.length) {
        api
            .search('client_id', searchValue.value)
            .search('client_secret', searchValue.value);
    }

    if (doItems) {
        api
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
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

// <editor-fold desc="View functions">

function createItem() {
    bus.emit('webhookEdit', {
        webhook: new Webhook(),
    });
}

function onEditItemBtnClicked(item: Webhook) {
    bus.emit('webhookEdit', {
        webhook: item,
    });
}

function onDeliveryListBtnClicked(item: Webhook) {
    bus.emit('webhookDeliveryList', {
        webhook: item,
    });
}

function deleteItem(item: Webhook) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.webhooks().deleteById(item.id!)
                    .delete(() => bus.emit('webhookSaved'));
            }
        }
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
            <v-toolbar-title>Webhooks</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn small class="" @click="createItem()"
                   prepend-icon="fa fa-plus"
            >
                Create
            </v-btn>
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
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onDeliveryListBtnClicked(item)">
                        <v-icon>fa fa-eye</v-icon>
                        <v-tooltip activator="parent" location="bottom">Deliveries</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onEditItemBtnClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red" size="small" icon
                        @click="deleteItem(item)">
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
