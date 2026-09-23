<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {ContainerRegistry} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: ContainerRegistry): void
}>();

const itemCount = ref(0);
const rows = ref<ContainerRegistry[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Provider', key: 'provider', sortable: true},
    {title: 'Auto update', key: 'events_enabled', sortable: true},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"name": "name", "provider": "provider", "events_enabled": "events_enabled"},
    defaultSort: {key: "name", order: "asc"},
});

onMounted(() => {
    bus.on('containerRegistrySaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('containerRegistrySaved', onItemSaved);
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
    const api = Api.containerRegistries().get();

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('provider', searchValue.value);
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

function onCreateItemBtnClicked() {
    bus.emit('containerRegistryEdit', {
        containerRegistry: new ContainerRegistry()
    });
}

function onEditItemBtnClicked(item: ContainerRegistry) {
    bus.emit('containerRegistryEdit', {
        containerRegistry: item
    });
    emit('onItemEditClicked', item);
}

function onImportItemBtnClicked(item: ContainerRegistry) {
    bus.emit('containerRegistryImport', {
        containerRegistry: item
    });
}

function onDeleteItemBtnClicked(item: ContainerRegistry) {
    bus.emit("integrationDelete", {
        name: item.name!,
        imageField: "container_registry_id",
        id: item.id!,
        imageDetail: image => image.url,
        note: "Webhooks kso set up on the registry stay there; remove them in the registry.",
        delete: done =>
            Api.containerRegistries()
                .deleteById(item.id!)
                .delete(() => {
                    bus.emit("containerRegistrySaved");
                    done();
                }),
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
            <v-toolbar-title>Container Registries</v-toolbar-title>

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
            <template v-slot:item.events_enabled="{ item }">
                <v-icon v-if="item.events_enabled" size="small">fa fa-check</v-icon>
            </template>
            <template v-slot:item.name="{ item }">
                <name-link @click="onEditItemBtnClicked(item)">{{ item.name }}</name-link>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <v-btn variant="plain" color="primary" @click="onEditItemBtnClicked(item)" size="small" density="comfortable" icon>
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="primary" @click="onImportItemBtnClicked(item)" size="small" density="comfortable" icon>
                        <v-icon>fa fa-download</v-icon>
                        <v-tooltip activator="parent" location="bottom">Import images</v-tooltip>
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
