<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {PodioIntegration} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: PodioIntegration): void
}>();

const itemCount = ref(0);
const rows = ref<PodioIntegration[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Client ID', key: 'client_id', sortable: true},
    {title: 'App ID', key: 'app_id', sortable: true},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"name": "name", "client_id": "client_id", "app_id": "app_id"},
    defaultSort: {key: "name", order: "asc"},
});

onMounted(() => {
    bus.on('podioIntegrationSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('podioIntegrationSaved', onItemSaved);
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
    const api = Api.podioIntegrations().get();

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('client_id', searchValue.value);
    }

    if (doItems) {
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

// <editor-fold desc="View functions">

function createItem() {
    bus.emit('podioIntegrationEdit', {
        podioIntegration: new PodioIntegration(),
    });
}

function onEditItemBtnClicked(item: PodioIntegration) {
    bus.emit('podioIntegrationEdit', {
        podioIntegration: item,
    });
}

function deleteItem(item: PodioIntegration) {
    bus.emit('confirm', {
        body: `Do you want to delete "${item.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.podioIntegrations().deleteById(item.id!)
                    .delete(() => bus.emit('podioIntegrationSaved'));
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
            <v-toolbar-title>Podio Integrations</v-toolbar-title>

            <v-text-field data-shortcut="search"
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn data-shortcut="create" small class="" @click="createItem()"
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
            <template v-slot:item.name="{ item }">
                <name-link @click="onEditItemBtnClicked(item)">{{ item.name }}</name-link>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">

                    <v-btn
                        variant="plain" color="primary"
                        @click="onEditItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red"
                        @click="deleteItem(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>
    </div>
</template>

<style scoped>

</style>
