<script setup lang="ts">
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
    {title: 'Client ID', key: 'client_id', sortable: false},
    {title: 'Client Secret', key: 'client_secret', sortable: false},
    {title: 'Grant Types', key: 'grant_types', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

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
    // Get options from DataTable
    const tableOptions: any = options.value;

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
            <template v-slot:item.name="{ item }">
                <span v-if="item.raw.user">{{ item.raw.user.name }}</span>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onEditItemBtnClicked(item.raw)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red" size="small" icon
                        @click="deleteItem(item.raw)">
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
