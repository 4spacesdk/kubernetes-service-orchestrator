<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {onMounted, onUnmounted, ref, watch} from 'vue'
import {useRouter} from "vue-router";
import {Project} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: Project): void
}>();

const router = useRouter();
const itemCount = ref(0);
const rows = ref<Project[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Description', key: 'description', sortable: false},
    {title: 'Members', key: 'users', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"name": "name"},
    defaultSort: {key: "name", order: "asc"},
});

onMounted(() => {
    bus.on('projectSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('projectSaved', onItemSaved);
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
    const api = Api.projects().get();

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('description', searchValue.value);
    }

    if (doItems) {
        applyPaging(api);
        applyOrdering(api);
        api
            .include('user')
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

function onCreateItemBtnClicked() {
    bus.emit('projectEdit', {
        project: new Project()
    });
}

function onEditItemBtnClicked(item: Project) {
    bus.emit('projectEdit', {
        project: item
    });
    emit('onItemEditClicked', item);
}

function onShowWorkspacesBtnClicked(item: Project) {
    router.push({name: 'WorkspacesByProject', params: {project: item.id}});
}

function onDeleteItemBtnClicked(item: Project) {
    bus.emit('confirm', {
        body: `Do you want to delete "${item.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'error',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.projects().deleteById(item.id!).delete(() => bus.emit('projectSaved', undefined));
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
            color="toolbar"
            dark
        >
            <v-toolbar-title>Projects</v-toolbar-title>

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
            <template v-slot:item.name="{ item }">
                <name-link @click="onEditItemBtnClicked(item)">{{ item.name }}</name-link>
            </template>
            <template v-slot:item.users="{ item }">
                {{ item.users?.length ?? 0 }}
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <v-btn variant="plain" color="primary" @click="onEditItemBtnClicked(item)" size="small" density="comfortable" icon>
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="primary" @click="onShowWorkspacesBtnClicked(item)" size="small" density="comfortable" icon>
                        <v-icon>fa fa-window-maximize</v-icon>
                        <v-tooltip activator="parent" location="bottom">Show workspaces</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="error" @click="onDeleteItemBtnClicked(item)" size="small" density="comfortable" icon>
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
