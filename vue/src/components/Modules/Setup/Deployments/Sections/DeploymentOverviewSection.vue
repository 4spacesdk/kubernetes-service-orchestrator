<script setup lang="ts">
import {Deployment} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import DateView from "@/components/Modules/Common/DateView.vue";
import DeploymentStatus from "@/components/Modules/Setup/Deployments/DeploymentStatus/DeploymentStatus.vue";
import DeploymentHealth from "@/components/Modules/Setup/Deployments/DeploymentHealth/DeploymentHealth.vue";
import DeploymentLastMigrationStatus
    from "@/components/Modules/Setup/Deployments/DeploymentLastMigrationStatus/DeploymentLastMigrationStatus.vue";
import DeploymentPodsButton from "@/components/Modules/Setup/Deployments/DeploymentPodsButton/DeploymentPodsButton.vue";

/**
 * What a deployment is and how it is doing, at a glance, with its pods below. What can be
 * changed is in the sections under it.
 */
const props = defineProps<{
    deployment: Deployment
}>();

</script>

<template>
    <page-section
        title="Overview"
        hide-save>
        <dl class="facts">
            <dt>Status</dt>
            <dd><deployment-status :deployment="props.deployment"/></dd>

            <dt>Health</dt>
            <dd>
                <deployment-health :deployment="props.deployment"/>
                <span
                    v-if="props.deployment.health_reason"
                    class="text-medium-emphasis ml-2">{{ props.deployment.health_reason }}</span>
            </dd>

            <template v-if="props.deployment.version">
                <dt>Version</dt>
                <dd>{{ props.deployment.version }}</dd>
            </template>

            <template v-if="props.deployment.image">
                <dt>Image</dt>
                <dd class="text-break">{{ props.deployment.image }}</dd>
            </template>

            <template v-if="props.deployment.environment">
                <dt>Environment</dt>
                <dd>{{ props.deployment.environment }}</dd>
            </template>

            <dt>Specification</dt>
            <dd>
                <router-link
                    v-if="props.deployment.deployment_specification"
                    :to="{name: 'DeploymentSpecificationById', params: {id: props.deployment.deployment_specification_id}}">
                    {{ props.deployment.deployment_specification.name }}
                </router-link>
            </dd>

            <template v-if="props.deployment.workspace">
                <dt>Workspace</dt>
                <dd>
                    <router-link :to="{name: 'WorkspaceById', params: {id: props.deployment.workspace_id}}">
                        {{ props.deployment.workspace.name_readable ?? props.deployment.workspace.name }}
                    </router-link>
                </dd>
            </template>

            <template v-if="props.deployment.url_external">
                <dt>External url</dt>
                <dd>
                    <a
                        :href="props.deployment.url_external"
                        target="_blank"
                        rel="noopener">{{ props.deployment.url_external }}</a>
                </dd>
            </template>

            <template v-if="props.deployment.url_internal">
                <dt>Internal url</dt>
                <dd>{{ props.deployment.url_internal }}</dd>
            </template>

            <template v-if="props.deployment.canMigrate">
                <dt>Last migration</dt>
                <dd><deployment-last-migration-status :deployment="props.deployment"/></dd>
            </template>

            <template v-if="props.deployment.last_updated">
                <dt>Last update</dt>
                <dd><date-view :date-string="props.deployment.last_updated"/></dd>
            </template>
        </dl>

        <h3 class="pods-title">Pods</h3>
        <deployment-pods-button
            class="pods"
            :deployment="props.deployment"
            :app="props.deployment.name"
            role="app"/>
    </page-section>
</template>

<style scoped>
.facts {
    display: grid;
    grid-template-columns: max-content 1fr;
    column-gap: 24px;
    row-gap: 10px;
    max-width: 900px;
    align-items: center;
}

.facts dt {
    font-size: 12px;
    color: rgba(var(--v-theme-on-background), 0.55);
}

.facts dd {
    display: flex;
    align-items: center;
    min-height: 24px;
}

.facts a {
    color: rgb(var(--v-theme-secondary));
}

.pods-title {
    margin: 24px 0 4px;
    font-size: 13px;
    font-weight: 600;
}

.pods {
    padding: 0 !important;
}
</style>
