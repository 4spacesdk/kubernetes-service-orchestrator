<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {WorkspaceTemplate, EmailService} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import WorkspaceTemplateEditButton
    from "@/components/Modules/Setup/WorkspaceTemplates/EditButton/WorkspaceTemplateEditButton.vue";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: WorkspaceTemplate): void
}>();

const itemCount = ref(0);
const rows = ref<WorkspaceTemplate[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Deployment Specifications', key: 'specifications', sortable: false},
    {title: 'Environment Variables', key: 'environment-variables', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"name": "name"},
    defaultSort: {key: "name", order: "asc"},
});

onMounted(() => {
    bus.on('workspaceTemplateSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('workspaceTemplateSaved', onItemSaved);
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
    const api = Api.workspaceTemplates().get()
        .include('workspace_template_deployment_specification')
        .include('workspace_template_environment_variable');

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

function createItem() {
    bus.emit('workspaceTemplateEdit', {
        workspaceTemplate: new WorkspaceTemplate(),
    });
}

function onDeleteItemBtnClicked(item: WorkspaceTemplate) {
    bus.emit('confirm', {
        body: `Do you want to delete "${item.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'error',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.workspaceTemplates().deleteById(item.id!).delete(() => bus.emit('workspaceTemplateSaved'));
            }
        }
    });
}

function onDuplicateItemBtnClicked(item: WorkspaceTemplate) {
    // The copy is made and saved by the server, children and all - they are edited in
    // dialogs of their own, which need a saved row. It is then read again the way the
    // list reads it, so the edit dialog gets the relations it shows.
    Api.workspaceTemplates().duplicatePostById(item.id!).save(null, copy => {
        bus.emit('workspaceTemplateSaved');
        Api.workspaceTemplates().getById(copy.id!).find(items => {
            bus.emit('workspaceTemplateEdit', {
                workspaceTemplate: items[0],
            });
        });
    });
}

function onEditItemBtnClicked(item: WorkspaceTemplate) {
    bus.emit('workspaceTemplateEdit', {
        workspaceTemplate: item
    });
    emit('onItemEditClicked', item);
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
            <v-toolbar-title>Workspace Templates</v-toolbar-title>

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
            <template v-slot:item.specifications="{ item }">
                <span>{{ item.workspace_template_deployment_specifications?.length }}</span>
            </template>
            <template v-slot:item.environment-variables="{ item }">
                <span>{{ item.workspace_template_environment_variables?.length }}</span>
            </template>

            <template v-slot:item.name="{ item }">

                <name-link @click="onEditItemBtnClicked(item)">{{ item.name }}</name-link>

            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">

                    <v-menu
                        min-width="250">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary"
                                size="small"
                                density="comfortable"
                                icon
                            >
                                <v-icon>fa fa-cog</v-icon>
                                <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                            </v-btn>
                        </template>
                        <workspace-template-edit-button
                            :workspace-template="item"/>
                    </v-menu>

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
                        variant="plain" color="error" 
                        @click="onDeleteItemBtnClicked(item)"
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
