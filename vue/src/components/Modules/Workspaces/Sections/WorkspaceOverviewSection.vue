<script setup lang="ts">
import {Workspace} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import DashboardCard from "@/components/Modules/Common/DetailPage/DashboardCard.vue";
import DateView from "@/components/Modules/Common/DateView.vue";
import WorkspaceHealth from "@/components/Modules/Workspaces/WorkspaceHealth/WorkspaceHealth.vue";
import WorkspaceDeploymentStatus from "@/components/Modules/Workspaces/WorkspaceDeploymentStatus/WorkspaceDeploymentStatus.vue";
import WorkspaceAddresses from "@/components/Modules/Workspaces/WorkspaceDeploymentDomains/WorkspaceAddresses.vue";

/**
 * A workspace at a glance: how it is doing and where it answers. What can be changed is in the
 * sections under it.
 */
const props = defineProps<{
    workspace: Workspace
}>();
</script>

<template>
    <page-section
        title="Overview"
        hide-save>
        <div class="dashboard">
            <dashboard-card
                title="Status"
                icon="fa fa-heart-pulse">
                <dl class="facts">
                    <dt>Status</dt>
                    <dd><workspace-deployment-status :workspace="props.workspace"/></dd>

                    <dt>Health</dt>
                    <dd><workspace-health :workspace="props.workspace"/></dd>

                    <template v-if="props.workspace.health_reason">
                        <dt>Why</dt>
                        <dd class="text-medium-emphasis">{{ props.workspace.health_reason }}</dd>
                    </template>

                    <template v-if="props.workspace.is_paused">
                        <dt>Paused</dt>
                        <dd>Yes - Deploy brings it back once it is resumed</dd>
                    </template>

                    <template v-if="props.workspace.project">
                        <dt>Project</dt>
                        <dd>{{ props.workspace.project.name }}</dd>
                    </template>

                    <template v-if="props.workspace.workspace_template">
                        <dt>Template</dt>
                        <dd>{{ props.workspace.workspace_template.name }}</dd>
                    </template>

                    <template v-if="props.workspace.created">
                        <dt>Created</dt>
                        <dd><date-view :date-string="props.workspace.created"/></dd>
                    </template>
                </dl>
            </dashboard-card>

            <dashboard-card
                title="Where it answers"
                icon="fa fa-globe">
                <workspace-addresses :workspace="props.workspace"/>
                <dl
                    v-if="props.workspace.aliases"
                    class="facts mt-3">
                    <dt>Aliases</dt>
                    <dd>{{ props.workspace.aliases }}</dd>
                </dl>
            </dashboard-card>
        </div>
    </page-section>
</template>

<style scoped>
.dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    align-items: start;
    gap: 16px;
    max-width: 1100px;
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
    color: rgba(var(--v-theme-on-background), 0.55);
}

.facts dd {
    display: flex;
    align-items: center;
    gap: 4px;
    min-height: 24px;
    min-width: 0;
}
</style>
