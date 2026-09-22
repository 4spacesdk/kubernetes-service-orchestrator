<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment, DeploymentSpecification, Workspace} from "@/core/services/Deploy/models";
import DeploymentList from "@/components/Modules/Setup/Deployments/List/DeploymentList.vue";
import DashboardCard from "@/components/Modules/Common/DetailPage/DashboardCard.vue";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

/**
 * A workspace's deployments, and making one from a specification it does not have yet.
 */
interface CreateItem {
    inUse: boolean;
    deploymentSpecification: DeploymentSpecification;
}

const props = defineProps<{
    workspace: Workspace;
}>();

const isLoadingCreateItems = ref(false);
const showCreateMenu = ref(false);
const createItems = ref<CreateItem[]>([]);

onMounted(() => {
    isLoadingCreateItems.value = true;
    Api.workspaces().get()
        .where('id', props.workspace.id!)
        .include('deployment')
        .find(workspaces => {
            const usedDeploymentSpecificationIds = workspaces[0].deployments
                ?.map(deployment => deployment.deployment_specification_id!)
                ?? []
            Api.deploymentSpecifications().get()
                .include('container_image')
                .find(items => {
                    createItems.value = items.map(item => {
                        return {
                            inUse: usedDeploymentSpecificationIds.includes(item.id!),
                            deploymentSpecification: item,
                        };
                    }).sort((a, b) => a.deploymentSpecification.name?.localeCompare(b.deploymentSpecification.name ?? '') ?? 0);
                    isLoadingCreateItems.value = false;
                });
        });
});

function onCreateItemBtnClicked(createItem: CreateItem) {
    showCreateMenu.value = false;
    bus.emit('deploymentCreate', {
        spec: createItem.deploymentSpecification,
        workspace: props.workspace,
        onSavedCallback: (deployment: Deployment) => onItemCreatedEvent(deployment),
    });
}

function onItemCreatedEvent(deployment: Deployment) {
    const createItem = createItems.value.find(createItem => createItem.deploymentSpecification.id == deployment.deployment_specification_id);
    if (createItem) {
        createItem.inUse = true;
    }
}

function onItemDeletedEvent(deployment: Deployment) {
    const createItem = createItems.value.find(createItem => createItem.deploymentSpecification.id == deployment.deployment_specification_id);
    if (createItem) {
        createItem.inUse = false;
    }
}

</script>

<template>
    <dashboard-card
        title="Deployments"
        icon="fa fa-cubes">
        <template #actions>
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
                        variant="text"
                        prepend-icon="fa fa-plus"
                        :loading="isLoadingCreateItems"
                    >
                        Create
                    </v-btn>
                </template>

                <v-list
                    class="list-items">
                    <div
                        v-for="(createItem, i) in createItems" :key="i"
                    >
                        <v-list-item
                            dense
                            :disabled="createItem.inUse"
                            @click="onCreateItemBtnClicked(createItem)"
                        >
                            <v-list-item-title>
                                <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                                <span class="ml-2">{{ createItem.deploymentSpecification.name }}</span>
                            </v-list-item-title>
                        </v-list-item>
                        <v-tooltip
                            v-if="createItem.inUse"
                            activator="parent" location="bottom">
                            Already in use
                        </v-tooltip>
                    </div>
                </v-list>
            </v-menu>
        </template>

        <deployment-list
            class="deployments"
            :show-header="false"
            :filter-by-workspace-id="props.workspace.id"
            @on-item-deleted="onItemDeletedEvent"
            @on-item-saved="onItemCreatedEvent"
        />
    </dashboard-card>
</template>

<style scoped>
.deployments {
    margin: -12px -16px;
}
</style>
