<script setup lang="ts">
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
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Deployment Specifications', key: 'specifications', sortable: false},
    {title: 'Environment Variables', key: 'environment-variables', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

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
    // Get options from DataTable
    const tableOptions: any = options.value;

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
        api
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderAsc('name')
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
            <template v-slot:item.specifications="{ item }">
                <span>{{ item.raw.deployment_package_deployment_specifications?.length }}</span>
            </template>
            <template v-slot:item.environment-variables="{ item }">
                <span>{{ item.raw.deployment_package_environment_variables?.length }}</span>
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">

                    <v-menu
                        min-width="250">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary" size="small" icon>
                                <v-icon>fa fa-cog</v-icon>
                                <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                            </v-btn>
                        </template>
                        <deployment-package-edit-button
                            :deployment-package="item.raw"/>
                    </v-menu>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onEditItemBtnClicked(item.raw)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red" size="small" icon
                        @click="onDeleteItemBtnClicked(item.raw)">
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
