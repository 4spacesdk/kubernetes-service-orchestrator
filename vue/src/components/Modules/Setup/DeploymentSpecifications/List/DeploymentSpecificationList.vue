<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import DeploymentSpecificationEditButton
    from "@/components/Modules/Setup/DeploymentSpecifications/EditButton/DeploymentSpecificationEditButton.vue";
import {WorkloadTypes} from "@/constants";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: DeploymentSpecification): void
}>();

const itemCount = ref(0);
const rows = ref<DeploymentSpecification[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Workload Type', key: 'workload_type', sortable: false},
    {title: 'Network Type', key: 'network_type', sortable: false},
    {title: 'Database', key: 'enable_database', sortable: false},
    {title: 'Ingress', key: 'ingress', sortable: false},
    {title: 'RBAC', key: 'enable_rbac', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

const showCreateMenu = ref(false);
const createItems = ref([
    {
        value: WorkloadTypes.Deployment,
        name: 'Kubernetes Deployment'
    },
    {
        value: WorkloadTypes.KNativeService,
        name: 'KNative Service'
    },
    {
        value: WorkloadTypes.DaemonSet,
        name: 'DaemonSet'
    },
    {
        value: WorkloadTypes.CustomResource,
        name: 'Custom Resource'
    },
]);

onMounted(() => {
    bus.on('deploymentSpecificationSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('deploymentSpecificationSaved', onItemSaved);
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
    const api = Api.deploymentSpecifications().get();

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value);
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

function createItem(type: string) {
    bus.emit('deploymentSpecificationEdit', {
        deploymentSpecification: DeploymentSpecification.Create(type),
    });
}

function onDeleteItemBtnClicked(item: DeploymentSpecification) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.deploymentSpecifications().deleteById(item.id!).delete(() => bus.emit('deploymentSpecificationSaved'));
            }
        }
    });
}

function onEditItemBtnClicked(item: DeploymentSpecification) {
    bus.emit('deploymentSpecificationEdit', {
        deploymentSpecification: item
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
            <v-toolbar-title>Deployment Specifications</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>

            <v-menu
                v-model="showCreateMenu"
                :close-on-content-click="false"
                left
                min-width="250"
                offset-y>
                <template v-slot:activator="{ props }">
                    <v-btn
                        v-bind="props"
                        small
                        prepend-icon="fa fa-plus">
                        Create
                    </v-btn>
                </template>

                <v-list
                    class="list-items">
                    <v-list-item
                        v-for="(type, i) in createItems" :key="i"
                        dense
                        @click="createItem(type.value)">
                        <v-list-item-title>
                            <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                            <span class="ml-2">{{ type.name }}</span>
                        </v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-menu>
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
            <template v-slot:item.enable_database="{ item }">
                <v-icon v-if="item.enable_database">fa fa-check</v-icon>
            </template>
            <template v-slot:item.ingress="{ item }">
                <span v-if="item.domain_tls">{{ item.domain_tls }}://{{ item.domain_prefix }}?{{ item.domain_suffix}}</span>
            </template>
            <template v-slot:item.enable_rbac="{ item }">
                <v-icon v-if="item.enable_rbac">fa fa-check</v-icon>
            </template>
            <template v-slot:item.network_type="{ item }">
                <span v-if="item.enable_external_access">{{ item.network_type }}</span>
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
                        <deployment-specification-edit-button
                            :deployment-specification="item"/>
                    </v-menu>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                           @click="onEditItemBtnClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red" size="small" icon
                           @click="onDeleteItemBtnClicked(item)">
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
