<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DatabaseService} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import { CopyNameStrategy, duplicateEntity } from "@/helpers/DuplicateEntity";

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
    {title: 'Name', key: 'item.name', sortable: true},
    {title: 'Driver', key: 'item.driver', sortable: true},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"item.name": "name", "item.driver": "driver"},
    defaultSort: {key: "item.name", order: "asc"},
});

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
        applyPaging(api);
        applyOrdering(api);
        api
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

function onDuplicateItemBtnClicked(row: Row) {
    // Read the row again rather than copying what the table holds, so fields the list
    // does not ask for still make it into the copy.
    Api.databaseServices().getById(row.item.id!).find(items => {
        bus.emit('databaseServiceEdit', {
            databaseService: duplicateEntity(items[0], DatabaseService, CopyNameStrategy.Label),
        });
    });
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

            <v-text-field data-shortcut="search"
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn data-shortcut="create" small class="" @click="onCreateItemBtnClicked()"
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
            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            v-model:sort-by="sortBy"
            class="table"
            density="compact"
            @update:options="options = $event; getItems()">
            <template v-slot:item.item.name="{ item }">
                <name-link @click="onEditItemBtnClicked(item)">{{ item.item.name }}</name-link>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <v-btn
                        variant="plain"
                        :color="item.testConnectionResult !== undefined ? (item.testConnectionResult ? 'success' : 'warning') : 'primary'"
                        @click="onTestConnectionBtnClicked(item)"
                        :loading="item.isLoadingTestConnection"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-wifi</v-icon>
                        <v-tooltip activator="parent" location="bottom">Test connection</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" @click="onEditItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="primary" @click="onDuplicateItemBtnClicked(item)" size="small" density="comfortable" icon>
                        <v-icon>fa fa-clone</v-icon>
                        <v-tooltip activator="parent" location="bottom">Duplicate</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="red" @click="onDeleteItemBtnClicked(item)" size="small" density="comfortable" icon>
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
