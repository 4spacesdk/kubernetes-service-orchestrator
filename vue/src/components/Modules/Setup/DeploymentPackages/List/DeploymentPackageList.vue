<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DeploymentPackage, EmailService} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import DeploymentPackageEditButton
    from "@/components/Modules/Setup/DeploymentPackages/EditButton/DeploymentPackageEditButton.vue";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: DeploymentPackage): void
}>();

const itemCount = ref(0);
const rows = ref<DeploymentPackage[]>([]);
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
    bus.on('deploymentPackageSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('deploymentPackageSaved', onItemSaved);
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
    const api = Api.deploymentPackages().get()
        .include('deployment_package_deployment_specification')
        .include('deployment_package_environment_variable');

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
    bus.emit('deploymentPackageEdit', {
        deploymentPackage: new DeploymentPackage(),
    });
}

function onDeleteItemBtnClicked(item: DeploymentPackage) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.deploymentPackages().deleteById(item.id!).delete(() => bus.emit('deploymentPackageSaved'));
            }
        }
    });
}

function onDuplicateItemBtnClicked(item: DeploymentPackage) {
    // The copy is made and saved by the server, children and all - they are edited in
    // dialogs of their own, which need a saved row. It is then read again the way the
    // list reads it, so the edit dialog gets the relations it shows.
    Api.deploymentPackages().duplicatePostById(item.id!).save(null, copy => {
        bus.emit('deploymentPackageSaved');
        Api.deploymentPackages().getById(copy.id!).find(items => {
            bus.emit('deploymentPackageEdit', {
                deploymentPackage: items[0],
            });
        });
    });
}

function onEditItemBtnClicked(item: DeploymentPackage) {
    bus.emit('deploymentPackageEdit', {
        deploymentPackage: item
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
            color="blue-grey lighten-5"
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
                <span>{{ item.deployment_package_deployment_specifications?.length }}</span>
            </template>
            <template v-slot:item.environment-variables="{ item }">
                <span>{{ item.deployment_package_environment_variables?.length }}</span>
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
                        <deployment-package-edit-button
                            :deployment-package="item"/>
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
                        variant="plain" color="red" 
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
