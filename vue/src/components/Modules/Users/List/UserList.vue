<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {User} from "@/core/services/Deploy/models";
import {useRoute, useRouter} from "vue-router";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: User): void
}>();

const itemCount = ref(0);
const rows = ref<User[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'E-mail', key: 'username', sortable: true},
    {title: 'Roles', key: 'roles', sortable: false},
    {title: '2FA', key: 'mfa', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"name": "name", "username": "username"},
    defaultSort: {key: "name", order: "asc"},
});

onMounted(() => {
    bus.on('userSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('userSaved', onItemSaved);
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
    const api = Api.users().get();

    if (searchValue.value?.length) {
        api
            .search('first_name', searchValue.value)
            .search('last_name', searchValue.value)
            .search('username', searchValue.value);
    }

    if (doItems) {
        applyPaging(api);
        applyOrdering(api);
        api
            .include('rbac_role')
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

function createItem() {
    bus.emit('userEdit', {
        user: new User()
    });
}

function editItem(item: User) {
    bus.emit('userEdit', {
        user: item
    });
    emit('onItemEditClicked', item);
}

function deleteItem(item: User) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.users().deleteById(item.id!).delete(() => bus.emit('userSaved'));
            }
        }
    });
}

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>Users</v-toolbar-title>

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
            <template v-slot:item.roles="{ item }">
                <div
                    class="d-flex gap-1"
                >
                    <v-chip
                        v-for="role in item.rbac_roles"
                        size="small"
                    >
                        {{ role.name }}
                        <v-tooltip activator="parent" location="bottom">{{ role.description }}</v-tooltip>
                    </v-chip>
                </div>
            </template>

            <template v-slot:item.mfa="{ item }">
                <v-icon v-if="item.has_mfa_secret_hash" color="success">
                    fa fa-check
                </v-icon>
            </template>

            <template v-slot:item.name="{ item }">

                <name-link @click="editItem(item)">{{ item.name }}</name-link>

            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <v-btn variant="plain" color="primary" @click="editItem(item)" size="small" density="comfortable" icon>
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="red" @click="deleteItem(item)" size="small" density="comfortable" icon>
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
