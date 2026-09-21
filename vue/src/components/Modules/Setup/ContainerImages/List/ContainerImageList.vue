<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {ContainerImage} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import { VersionControlProviders } from "@/constants";
import { CopyNameStrategy, duplicateEntity } from "@/helpers/DuplicateEntity";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: ContainerImage): void
}>();

const itemCount = ref(0);
const rows = ref<ContainerImage[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Url', key: 'url', sortable: true},
    {title: 'Pull secret', key: 'pull_secret', sortable: true},
    {title: 'Registry', key: 'container_registry', sortable: true},
    {title: 'VCS', key: 'version_control_provider', sortable: true},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"name": "name", "url": "url", "pull_secret": "pull_secret", "container_registry": "container_registry.name", "version_control_provider": "version_control_provider"},
    defaultSort: {key: "name", order: "asc"},
});

onMounted(() => {
    bus.on('containerImageSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('containerImageSaved', onItemSaved);
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
    const api = Api.containerImages().get()
        .include('container_registry');

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('url', searchValue.value);
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

function onImportBtnClicked() {
    bus.emit('containerRegistryImport', {});
}

function createItem() {
    bus.emit('containerImageEdit', {
        containerImage: ContainerImage.Create(),
    });
}

function onEditItemBtnClicked(item: ContainerImage) {
    bus.emit('containerImageEdit', {
        containerImage: item,
    });
}

function onTagsItemBtnClicked(item: ContainerImage) {
    bus.emit('containerImageTags', {
        containerImage: item,
    });
}

function onDuplicateItemBtnClicked(item: ContainerImage) {
    // Read the row again rather than copying what the table holds, so fields the list
    // does not ask for still make it into the copy.
    Api.containerImages().getById(item.id!).find(items => {
        bus.emit('containerImageEdit', {
            containerImage: duplicateEntity(items[0], ContainerImage, CopyNameStrategy.Label),
        });
    });
}

function deleteItem(item: ContainerImage) {
    bus.emit('confirm', {
        body: `Do you want to delete "${item.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.containerImages().deleteById(item.id!).delete(() => bus.emit('containerImageSaved'));
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
            <v-toolbar-title>Container Images</v-toolbar-title>

            <v-text-field data-shortcut="search"
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn small class="" @click="onImportBtnClicked()"
                   prepend-icon="fa fa-download"
            >
                Import
            </v-btn>
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
            <template v-slot:item.container_registry="{ item }">
                <span>{{ item.container_registry?.name }}</span>
            </template>
            <template v-slot:item.version_control_provider="{ item }">
                <span v-if="item.version_control_enabled">
                    {{ item.version_control_provider }}
                    <v-tooltip
                        v-if="item.version_control_provider === VersionControlProviders.GitHub && item.version_control_repository_name"
                        activator="parent"
                        location="bottom"
                    >
                        {{ item.version_control_repository_name }}
                    </v-tooltip>
                </span>
            </template>
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
                        v-if="item.container_registry_id"
                        variant="plain" color="primary"
                        @click="onTagsItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-tags</v-icon>
                        <v-tooltip activator="parent" location="bottom">List tags</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" 
                        @click="onDuplicateItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-clone</v-icon>
                        <v-tooltip activator="parent" location="bottom">Duplicate</v-tooltip>
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
