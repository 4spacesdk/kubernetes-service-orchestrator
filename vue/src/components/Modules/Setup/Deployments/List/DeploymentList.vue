<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {Deployment, DeploymentSpecification} from "@/core/services/Deploy/models";
import DeploymentEditButton from "@/components/Modules/Setup/Deployments/EditButton/DeploymentEditButton.vue";
import DeploymentStatus from "@/components/Modules/Setup/Deployments/DeploymentStatus/DeploymentStatus.vue";
import DeploymentLastMigrationStatus
    from "@/components/Modules/Setup/Deployments/DeploymentLastMigrationStatus/DeploymentLastMigrationStatus.vue";
import DateView from "@/components/Modules/Common/DateView.vue";
import debounce from "lodash.debounce";
import DeploymentPodsButton from "@/components/Modules/Setup/Deployments/DeploymentPodsButton/DeploymentPodsButton.vue";

const props = defineProps<{
    filterByWorkspaceId?: number;

    showHeader: boolean;
    showCreateBtn?: boolean;
}>();

const emit = defineEmits<{
    (e: 'onItemDeleted', item: Deployment): void;
    (e: 'onItemSaved', item: Deployment): void;
}>();

const itemCount = ref(0);
const rows = ref<Deployment[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: true},
    {title: 'Namespace', key: 'namespace', sortable: true},
    {title: 'Status', key: 'status', sortable: false},
    {title: 'Last Migration', key: 'last-migration', sortable: false},
    {title: 'Version', key: 'version', sortable: false},
    {title: 'Last Update', key: 'last_updated', sortable: true},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const showCreateMenu = ref(false);
const deploymentSpecs = ref<DeploymentSpecification[]>([]);

const searchValue = ref('');

onMounted(() => {
    bus.on('deploymentSaved', onItemSaved);

    getItems(false, true);

    if (deploymentSpecs.value.length == 0) {
        Api.deploymentSpecifications().get()
            .include('container_image')
            .orderAsc('name')
            .find(items => {
                deploymentSpecs.value = items;
            });
    }
});

onUnmounted(() => {
    bus.off('deploymentSaved', onItemSaved);
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

    // Prepare api call
    const api = Api.deployments().get();

    if (props.filterByWorkspaceId) {
        api.where('workspace_id', props.filterByWorkspaceId);
    }

    if (searchValue.value?.length) {
        api
            .search('aliases', searchValue.value)
            .search('name', searchValue.value)
            .search('namespace', searchValue.value)
            .search('subdomain', searchValue.value)
            .search('status', searchValue.value)
            .search('version', searchValue.value);
    }

    if (doItems) {
        api
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1));

        const sortByKey = tableOptions.sortBy.length > 0 ? tableOptions.sortBy[0].key : 'name';
        const sortByOrder = tableOptions.sortBy.length > 0 ? tableOptions.sortBy[0].order : 'asc';
        switch (sortByKey) {
            case 'name':
                api.orderBy('name', sortByOrder);
                break;
            case 'last_updated':
                api.orderBy('last_updated', sortByOrder);
                break;
        }

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

// <editor-fold desc="View function">

function onCreateItemBtnClicked(deploymentSpec: DeploymentSpecification) {
    showCreateMenu.value = false;
    bus.emit('deploymentCreate', {
        spec: deploymentSpec,
        onSavedCallback: (deployment: Deployment) => emit('onItemSaved', deployment),
    });
}

function onDeleteItemBtnClicked(item: Deployment) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.deployments().deleteById(item.id!).delete(() => bus.emit('deploymentSaved'));
                emit('onItemDeleted', item);
            }
        }
    });
}

function onShowResourcesBtnClicked(item: Deployment) {
    bus.emit('deploymentResourceList', {
        deployment: item,
    });
}

function onShowMigrationJobsBtnClicked(item: Deployment) {
    bus.emit('migrationJobList', {
        deployment: item,
    });
}

// </editor-fold>

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            v-if="props.showHeader"
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>Deployments</v-toolbar-title>

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
                v-if="props.showCreateBtn"
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
                        v-for="(spec, i) in deploymentSpecs" :key="i"
                        dense
                        @click="onCreateItemBtnClicked(spec)">
                        <v-list-item-title>
                            <v-icon size="small" class="my-auto ml-2">fa fa-window-maximize fa</v-icon>
                            <span class="ml-2">{{ spec.name }}</span>
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

            <template v-slot:item.status="{ item }">
                <deployment-status
                    :deployment="item"/>
            </template>

            <template v-slot:item.last-migration="{ item }">
                <DeploymentLastMigrationStatus
                    v-if="item.canMigrate"
                    :deployment="item"/>
            </template>

            <template v-slot:item.last_updated="{ item }">
                <DateView
                    v-if="item.last_updated"
                    :date-string="item.last_updated"/>
            </template>

            <template v-slot:item.actions="{ item }">

                <div class="d-flex justify-end">

                    <v-menu
                        width="500">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary" size="small" icon
                            >
                                <v-icon>fa fa-server</v-icon>
                                <v-tooltip activator="parent" location="bottom">Pods</v-tooltip>
                            </v-btn>
                        </template>
                        <deployment-pods-button
                            :deployment="item"
                            :app="item.name"
                            role="app"
                        />
                    </v-menu>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowResourcesBtnClicked(item)">
                        <v-icon>fa fa-box</v-icon>
                        <v-tooltip activator="parent" location="bottom">Resources</v-tooltip>
                    </v-btn>

                    <v-btn
                        :disabled="!item.canMigrate"
                        variant="plain" color="primary" size="small" icon
                        @click="onShowMigrationJobsBtnClicked(item)">
                        <v-icon>fa fa-truck-arrow-right</v-icon>
                        <v-tooltip activator="parent" location="bottom">Migration Jobs</v-tooltip>
                    </v-btn>

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
                        <deployment-edit-button
                            :deployment="item"/>
                    </v-menu>

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
