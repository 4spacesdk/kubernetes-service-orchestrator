<script setup lang="ts">
import {computed, onMounted, onUnmounted, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {goBack} from "@/helpers/goBack";
import {Api} from "@/core/services/Deploy/Api";
import {Workspace} from "@/core/services/Deploy/models";
import {DeploymentStatusTypes} from "@/constants";
import {useWorkspaceActions} from "@/composables/useWorkspaceActions";
import DashboardCard from "@/components/Modules/Common/DetailPage/DashboardCard.vue";
import DateView from "@/components/Modules/Common/DateView.vue";
import WorkspaceHealth from "@/components/Modules/Workspaces/WorkspaceHealth/WorkspaceHealth.vue";
import WorkspaceDeploymentStatus from "@/components/Modules/Workspaces/WorkspaceDeploymentStatus/WorkspaceDeploymentStatus.vue";
import WorkspaceAddresses from "@/components/Modules/Workspaces/WorkspaceDeploymentDomains/WorkspaceAddresses.vue";
import WorkspaceDeployments from "@/components/Modules/Workspaces/Deployments/WorkspaceDeployments.vue";

/**
 * A workspace at a glance: how it is doing, where it answers, what it is set up with and what
 * it runs. Each deployment opens on a page of its own.
 */
const route = useRoute();
const router = useRouter();

const item = ref<Workspace>();
const isNotFound = ref(false);

const id = computed(() => parseInt(route.params.id as string));

const {
    rbacDeveloper,
    rbacWorkspaceCreate,
    rbacWorkspaceUpdate,
    deploy,
    terminate,
    pause,
    resume,
    remove,
    showMigrationJobs,
    showHistory,
    showLogs,
} = useWorkspaceActions();

/** The database a workspace uses can only change before anything of it has been deployed. */
const canChangeDatabaseService = computed(() => !(item.value?.deployments ?? [])
    .some(deployment => deployment.status !== DeploymentStatusTypes.Draft));

onMounted(() => {
    bus.on('workspaceSaved', onSaved);
    bus.on('deploymentSaved', onSaved);
    load();
});

onUnmounted(() => {
    bus.off('workspaceSaved', onSaved);
    bus.off('deploymentSaved', onSaved);
});

watch(id, () => {
    item.value = undefined;
    load();
});

function load() {
    isNotFound.value = false;
    Api.workspaces().getById(id.value)
        .include('deployment')
        .include('domain')
        .include('email_service')
        .include('database_service')
        .include('label')
        .include('workspace_template')
        .find(items => {
            item.value = items[0];
            isNotFound.value = !items[0];
            if (items[0]) {
                document.title = items[0].name_readable ?? document.title;
            }
        });
}

function onSaved() {
    load();
}

function onBack() {
    goBack(router, {name: 'Workspaces'});
}

function edit(event: 'workspaceUpdateName' | 'workspaceUpdateIngress' | 'workspaceUpdateEmailService' | 'workspaceUpdateDatabaseService' | 'workspaceUpdateLabels') {
    bus.emit(event, {workspace: item.value!});
}

</script>

<template>
    <div class="h-100 content-wrapper d-flex flex-column">
        <v-toolbar
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-btn
                icon
                size="small"
                @click="onBack">
                <v-icon>fa fa-arrow-left</v-icon>
                <v-tooltip activator="parent" location="bottom">Back</v-tooltip>
            </v-btn>
            <v-toolbar-title>
                <template v-if="item">
                    <span>{{ item.name_readable }}</span>
                    <span class="namespace ml-2">{{ item.namespace }}</span>
                </template>
            </v-toolbar-title>

            <template v-if="item">
                <v-btn
                    v-if="rbacWorkspaceCreate"
                    prepend-icon="fa fa-play"
                    @click="deploy(item)">
                    Deploy
                </v-btn>
                <v-menu
                    v-if="rbacDeveloper || rbacWorkspaceUpdate"
                    location="bottom end">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            aria-label="More"
                            icon
                            size="small">
                            <v-icon>fa fa-ellipsis-vertical</v-icon>
                        </v-btn>
                    </template>
                    <v-list density="compact">
                        <v-list-item
                            v-if="rbacDeveloper"
                            prepend-icon="fa fa-rectangle-list"
                            title="Kubernetes Logs"
                            @click="showLogs(item)"
                        />
                        <v-list-item
                            v-if="rbacDeveloper"
                            prepend-icon="fa fa-truck-arrow-right"
                            title="Migration Jobs"
                            @click="showMigrationJobs(item)"
                        />
                        <v-list-item
                            v-if="rbacDeveloper"
                            prepend-icon="fa fa-clock-rotate-left"
                            title="History"
                            @click="showHistory(item)"
                        />
                        <v-divider class="my-1"/>
                        <v-list-item
                            v-if="rbacDeveloper && !item.is_paused"
                            prepend-icon="fa fa-pause"
                            title="Pause"
                            base-color="red"
                            @click="pause(item)"
                        />
                        <v-list-item
                            v-if="rbacDeveloper && item.is_paused"
                            prepend-icon="fa fa-play"
                            title="Resume"
                            @click="resume(item)"
                        />
                        <v-list-item
                            v-if="rbacDeveloper"
                            prepend-icon="fa fa-skull"
                            title="Terminate"
                            base-color="red"
                            @click="terminate(item)"
                        />
                        <v-list-item
                            v-if="rbacWorkspaceUpdate"
                            prepend-icon="fa fa-trash"
                            title="Delete"
                            base-color="red"
                            @click="remove(item, {onDeleted: onBack})"
                        />
                    </v-list>
                </v-menu>
            </template>
        </v-toolbar>

        <div
            v-if="isNotFound"
            class="pa-4">
            This workspace does not exist.
        </div>

        <div
            v-else-if="item"
            class="dashboard">

            <dashboard-card
                title="Status"
                icon="fa fa-heart-pulse">
                <dl class="facts">
                    <dt>Status</dt>
                    <dd><workspace-deployment-status :workspace="item"/></dd>

                    <dt>Health</dt>
                    <dd>
                        <workspace-health :workspace="item"/>
                    </dd>

                    <template v-if="item.health_reason">
                        <dt>Why</dt>
                        <dd class="text-medium-emphasis">{{ item.health_reason }}</dd>
                    </template>

                    <template v-if="item.is_paused">
                        <dt>Paused</dt>
                        <dd>Yes - Deploy brings it back once it is resumed</dd>
                    </template>

                    <template v-if="item.workspace_template">
                        <dt>Template</dt>
                        <dd>{{ item.workspace_template.name }}</dd>
                    </template>

                    <template v-if="item.created">
                        <dt>Created</dt>
                        <dd><date-view :date-string="item.created"/></dd>
                    </template>
                </dl>
            </dashboard-card>

            <dashboard-card
                title="Where it answers"
                icon="fa fa-globe">
                <workspace-addresses :workspace="item"/>
                <dl
                    v-if="item.aliases"
                    class="facts mt-3">
                    <dt>Aliases</dt>
                    <dd>{{ item.aliases }}</dd>
                </dl>
            </dashboard-card>

            <dashboard-card
                title="Settings"
                icon="fa fa-sliders">
                <dl class="facts settings">
                    <dt>Name</dt>
                    <dd>
                        <span>{{ item.name_readable }}</span>
                        <v-btn
                            v-if="rbacWorkspaceUpdate"
                            icon="fa fa-pen"
                            variant="plain"
                            size="x-small"
                            aria-label="Change the name"
                            @click="edit('workspaceUpdateName')"/>
                    </dd>

                    <dt>Domain</dt>
                    <dd>
                        <span>{{ [item.subdomain, item.domain?.name].filter(part => part).join('.') || '—' }}</span>
                        <v-btn
                            v-if="rbacWorkspaceUpdate"
                            icon="fa fa-pen"
                            variant="plain"
                            size="x-small"
                            aria-label="Change the domain"
                            @click="edit('workspaceUpdateIngress')"/>
                    </dd>

                    <dt>Email service</dt>
                    <dd>
                        <span>{{ item.email_service?.name ?? '—' }}</span>
                        <v-btn
                            v-if="rbacWorkspaceUpdate"
                            icon="fa fa-pen"
                            variant="plain"
                            size="x-small"
                            aria-label="Change the email service"
                            @click="edit('workspaceUpdateEmailService')"/>
                    </dd>

                    <dt>Database service</dt>
                    <dd>
                        <span>{{ item.database_service?.name ?? '—' }}</span>
                        <v-btn
                            v-if="rbacWorkspaceUpdate"
                            :disabled="!canChangeDatabaseService"
                            icon="fa fa-pen"
                            variant="plain"
                            size="x-small"
                            aria-label="Change the database service"
                            @click="edit('workspaceUpdateDatabaseService')"/>
                        <v-tooltip
                            v-if="rbacWorkspaceUpdate && !canChangeDatabaseService"
                            activator="parent"
                            location="bottom">Only while none of its deployments has been deployed</v-tooltip>
                    </dd>

                    <dt>Labels</dt>
                    <dd>
                        <span class="d-flex flex-wrap ga-1">
                            <v-chip
                                v-for="label in item.labels ?? []"
                                :key="label.id"
                                size="x-small">{{ label.name }}={{ label.value }}</v-chip>
                            <span v-if="!item.labels?.length">—</span>
                        </span>
                        <v-btn
                            v-if="rbacWorkspaceUpdate"
                            icon="fa fa-pen"
                            variant="plain"
                            size="x-small"
                            aria-label="Change the labels"
                            @click="edit('workspaceUpdateLabels')"/>
                    </dd>
                </dl>
            </dashboard-card>

            <workspace-deployments
                class="wide"
                :key="item.id"
                :workspace="item"/>
        </div>

        <v-progress-linear
            v-else
            indeterminate/>
    </div>
</template>

<style scoped>
.namespace {
    font-size: 12px;
    opacity: 0.7;
}

.dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    align-items: start;
    gap: 16px;
    padding: 16px;
    overflow-y: auto;
}

.wide {
    grid-column: 1 / -1;
}

.facts {
    display: grid;
    grid-template-columns: max-content 1fr;
    column-gap: 16px;
    row-gap: 8px;
    align-items: center;
}

.facts dt {
    font-size: 12px;
    color: rgba(0, 0, 0, 0.55);
}

.facts dd {
    display: flex;
    align-items: center;
    gap: 4px;
    min-height: 24px;
    min-width: 0;
}

.settings dd {
    justify-content: space-between;
}

.settings dd > span:first-child {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
