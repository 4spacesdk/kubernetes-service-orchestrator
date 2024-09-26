<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DatabaseService} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: DatabaseService): void
}>();

interface Row {
    item: DatabaseService;
    isLoadingTestConnection: boolean;
    testConnectionResult?: boolean;
}

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Name', key: 'item.name', sortable: false},
    {title: 'Driver', key: 'item.driver', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

onMounted(() => {
    bus.on('databaseServiceSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('databaseServiceSaved', onItemSaved);
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
    const api = Api.databaseServices().get();

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('azure_host', searchValue.value)
            .search('host', searchValue.value)
            .search('ip', searchValue.value)
            .search('pass', searchValue.value)
            .search('user', searchValue.value);
    }

    if (doItems) {
        api
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderAsc('name')
            .find(items => {
                rows.value = items.map(item => {
                    return {
                        item: item,
                        isLoadingTestConnection: false,
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

// <editor-fold desc="View functions">

function onCreateItemBtnClicked() {
    bus.emit('databaseServiceEdit', {
        databaseService: new DatabaseService()
    });
}

function onEditItemBtnClicked(row: Row) {
    bus.emit('databaseServiceEdit', {
        databaseService: row.item
    });
    emit('onItemEditClicked', row.item);
}

function onDeleteItemBtnClicked(row: Row) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${row.item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.databaseServices().deleteById(row.item.id!).delete(() => bus.emit('databaseServiceSaved'));
            }
        }
    });
}

function onTestConnectionBtnClicked(row: Row) {
    row.isLoadingTestConnection = true;
    Api.databaseServices().testConnectionGetById(row.item.id!)
        .find(value => {
            bus.emit('info', {
                title: value[0].value ? 'Success' : 'Failed',
                body: value[0].value ? 'Connection confirmed' : 'Failed to connect',
            });
            row.testConnectionResult = value[0].value;
            row.isLoadingTestConnection = false;
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
            <v-toolbar-title>Database Services</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn small class="" @click="onCreateItemBtnClicked()"
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
                        variant="plain"
                        :color="item.testConnectionResult !== undefined ? (item.testConnectionResult ? 'success' : 'warning') : 'primary'"
                        size="small"
                        @click="onTestConnectionBtnClicked(item)"
                        :loading="item.isLoadingTestConnection"
                    >
                        <v-icon>fa fa-wifi</v-icon>
                        <v-tooltip activator="parent" location="bottom">Test connection</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small" @click="onEditItemBtnClicked(item)"
                    >
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="red" size="small" @click="onDeleteItemBtnClicked(item)">
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
