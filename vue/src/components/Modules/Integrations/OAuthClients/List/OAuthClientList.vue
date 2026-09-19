<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {OAuthClient} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: OAuthClient): void
}>();

const itemCount = ref(0);
const rows = ref<OAuthClient[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Client ID', key: 'client_id', sortable: true},
    {title: 'Client Secret', key: 'client_secret', sortable: false},
    {title: 'Grant Types', key: 'grant_types', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"client_id": "client_id"},
    defaultSort: {key: "client_id", order: "asc"},
});

onMounted(() => {
    bus.on('oauthClientSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('oauthClientSaved', onItemSaved);
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
    const api = Api.oAuthClients().get()
        .include('user');

    if (searchValue.value?.length) {
        api
            .search('client_id', searchValue.value)
            .search('client_secret', searchValue.value);
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
    bus.emit('oauthClientEdit', {
        oAuthClient: new OAuthClient(),
    });
}

function onEditItemBtnClicked(item: OAuthClient) {
    bus.emit('oauthClientEdit', {
        oAuthClient: item,
    });
}

function deleteItem(item: OAuthClient) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.client_id}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.oAuthClients().deleteDeleteById(item.client_id!)
                    .delete(() => bus.emit('oauthClientSaved'));
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
            <v-toolbar-title>OAuth Clients</v-toolbar-title>

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
                <name-link v-if="item.user" @click="onEditItemBtnClicked(item)">{{ item.user.name }}</name-link>
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
.table > *,
.table {
    background: transparent;
}
</style>
