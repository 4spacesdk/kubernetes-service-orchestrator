import {defineAsyncComponent} from "vue";
import {groupSections, type DetailSection, type DetailSectionGroup} from "@/components/Modules/Common/DetailPage/detailSections";
import type {Deployment} from "@/core/services/Deploy/models";
import {DeploymentStatusTypes, WorkloadTypes} from "@/constants";

export type DeploymentSection = DetailSection<Deployment>;

const section = (loader: () => Promise<any>) => defineAsyncComponent(loader);

/** What a deployment has follows from its specification, which the page reads with it. */
const isCustomResource = (deployment: Deployment) => deployment.deployment_specification?.workload_type == WorkloadTypes.CustomResource;

/**
 * Everything a deployment has, in the order the side menu shows it. The settings menu in the
 * list reads the same.
 */
export const deploymentSections: DeploymentSection[] = [
    {
        key: '',
        title: 'Overview',
        icon: 'fa fa-gauge',
        group: '',
        isShown: () => true,
        component: section(() => import('./DeploymentOverviewSection.vue')),
    },
    {
        key: 'resources',
        title: 'Resources',
        icon: 'fa fa-box',
        group: '',
        isShown: () => true,
        component: section(() => import('./DeploymentResourcesSection.vue')),
    },
    {
        key: 'logs',
        title: 'Kubernetes Logs',
        icon: 'fa fa-rectangle-list',
        group: '',
        isShown: () => true,
        component: section(() => import('./DeploymentLogsSection.vue')),
    },
    {
        key: 'migration-jobs',
        title: 'Migration Jobs',
        icon: 'fa fa-truck-arrow-right',
        group: '',
        isShown: deployment => deployment.canMigrate,
        component: section(() => import('./DeploymentMigrationJobsSection.vue')),
    },
    {
        key: 'migration-logs',
        title: 'Migration Logs',
        icon: 'fa fa-scroll',
        group: '',
        isShown: deployment => deployment.canMigrate,
        component: section(() => import('./DeploymentMigrationLogsSection.vue')),
    },
    {
        key: 'history',
        title: 'History',
        icon: 'fa fa-clock-rotate-left',
        group: '',
        isShown: () => true,
        component: section(() => import('./DeploymentHistorySection.vue')),
    },

    {
        key: 'version',
        title: 'Version',
        icon: 'fa fa-code-branch',
        group: 'Settings',
        isShown: deployment => !isCustomResource(deployment),
        component: section(() => import('./DeploymentVersionSection.vue')),
    },
    {
        key: 'image-pull-policy',
        title: 'Image Pull Policy',
        icon: 'fa fa-code-pull-request',
        group: 'Settings',
        isShown: deployment => !isCustomResource(deployment),
        component: section(() => import('./DeploymentImagePullPolicySection.vue')),
    },
    {
        key: 'environment',
        title: 'Environment',
        icon: 'fa fa-file-code',
        group: 'Settings',
        isShown: deployment => !isCustomResource(deployment),
        component: section(() => import('./DeploymentEnvironmentSection.vue')),
    },
    {
        key: 'database-service',
        title: 'Database Service',
        icon: 'fa fa-database',
        group: 'Settings',
        isShown: deployment => deployment.deployment_specification?.enable_database ?? false,
        isEnabled: deployment => deployment.status == DeploymentStatusTypes.Draft,
        disabledHint: 'Only available while the deployment is a draft',
        component: section(() => import('./DeploymentDatabaseServiceSection.vue')),
    },
    {
        key: 'resource-management',
        title: 'Resource Management',
        icon: 'fa fa-plug-circle-bolt',
        group: 'Settings',
        isShown: deployment => !isCustomResource(deployment),
        component: section(() => import('./DeploymentResourceManagementSection.vue')),
    },
    {
        key: 'knative-min-scale-schedules',
        title: 'KNative Min Scale Schedules',
        icon: 'fa fa-clock',
        group: 'Settings',
        isShown: deployment => deployment.deployment_specification?.workload_type == WorkloadTypes.KNativeService,
        component: section(() => import('./DeploymentKNativeMinScaleSchedulesSection.vue')),
    },
    {
        key: 'update-management',
        title: 'Update Management',
        icon: 'fa fa-arrows-rotate',
        group: 'Settings',
        isShown: deployment => !isCustomResource(deployment),
        component: section(() => import('./DeploymentUpdateManagementSection.vue')),
    },

    {
        key: 'environment-variables',
        title: 'Environment Variables',
        icon: 'fa fa-key',
        group: 'Configuration',
        isShown: deployment => !isCustomResource(deployment),
        component: section(() => import('./DeploymentEnvironmentVariablesSection.vue')),
    },
    {
        key: 'volumes',
        title: 'Volumes',
        icon: 'fa fa-hard-drive',
        group: 'Configuration',
        isShown: deployment => deployment.deployment_specification?.enable_volumes ?? false,
        component: section(() => import('./DeploymentVolumesSection.vue')),
    },
    {
        key: 'labels',
        title: 'Labels',
        icon: 'fa fa-tags',
        group: 'Configuration',
        isShown: () => true,
        component: section(() => import('./DeploymentLabelsSection.vue')),
    },
    {
        key: 'cron-jobs',
        title: 'Cron Jobs',
        icon: 'fa fa-clock',
        group: 'Configuration',
        isShown: deployment => deployment.deployment_specification?.enable_cronjob ?? false,
        component: section(() => import('./DeploymentCronJobsSection.vue')),
    },

    {
        key: 'terminate',
        title: 'Terminate',
        icon: 'fa fa-skull',
        group: 'Advanced',
        isShown: () => true,
        component: section(() => import('./DeploymentTerminateSection.vue')),
    },
    {
        key: 'delete',
        title: 'Delete',
        icon: 'fa fa-trash',
        group: 'Advanced',
        isShown: () => true,
        component: section(() => import('./DeploymentDeleteSection.vue')),
    },
];

/** The shown sections, grouped, in order. */
export function groupedDeploymentSections(deployment: Deployment): DetailSectionGroup<Deployment>[] {
    return groupSections(deploymentSections, deployment);
}
