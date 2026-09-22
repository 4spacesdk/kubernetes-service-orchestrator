import {defineAsyncComponent} from "vue";
import {groupSections, type DetailSection, type DetailSectionGroup} from "@/components/Modules/Common/DetailPage/detailSections";
import type {DeploymentSpecification} from "@/core/services/Deploy/models";
import {NetworkTypes, WorkloadTypes} from "@/constants";

export type DeploymentSpecificationSection = DetailSection<DeploymentSpecification>;

const section = (loader: () => Promise<any>) => defineAsyncComponent(loader);

const isCustomResource = (spec: DeploymentSpecification) => spec.workload_type == WorkloadTypes.CustomResource;
const hasExternalAccess = (spec: DeploymentSpecification) => spec.enable_external_access ?? false;
const hasInternalAccess = (spec: DeploymentSpecification) => spec.enable_internal_access ?? false;

/**
 * Everything a deployment specification is made of, in the order the side menu shows it.
 * The settings menu in the list reads the same, so the two cannot disagree on what a
 * specification has.
 */
export const deploymentSpecificationSections: DeploymentSpecificationSection[] = [
    {
        key: '',
        title: 'General',
        icon: 'fa fa-sliders',
        group: '',
        isShown: () => true,
        component: section(() => import('./DeploymentSpecificationGeneralSection.vue')),
    },

    {
        key: 'init-containers',
        title: 'Init Containers',
        icon: 'fa fa-box',
        group: 'Workload',
        isShown: spec => !isCustomResource(spec),
        component: section(() => import('./DeploymentSpecificationInitContainersSection.vue')),
    },
    {
        key: 'environment-variables',
        title: 'Environment Variables',
        icon: 'fa fa-key',
        group: 'Workload',
        isShown: spec => !isCustomResource(spec),
        component: section(() => import('./DeploymentSpecificationEnvironmentVariablesSection.vue')),
    },
    {
        key: 'annotations',
        title: 'Annotations',
        icon: 'fa fa-tags',
        group: 'Workload',
        isShown: spec => !isCustomResource(spec),
        component: section(() => import('./DeploymentSpecificationDeploymentAnnotationsSection.vue')),
    },

    {
        key: 'ingresses',
        title: 'Ingresses',
        icon: 'fa fa-link',
        group: 'Network',
        isShown: spec => hasExternalAccess(spec) && spec.network_type == NetworkTypes.NginxIngress,
        component: section(() => import('./DeploymentSpecificationIngressesSection.vue')),
    },
    {
        key: 'http-proxy-routes',
        title: 'Http Proxy Routes',
        icon: 'fa fa-link',
        group: 'Network',
        isShown: spec => hasExternalAccess(spec)
            && [NetworkTypes.Contour, NetworkTypes.GatewayApi].includes(spec.network_type ?? ''),
        component: section(() => import('./DeploymentSpecificationHttpProxyRoutesSection.vue')),
    },
    {
        key: 'service-ports',
        title: 'Service Ports',
        icon: 'fa fa-hashtag',
        group: 'Network',
        isShown: spec => hasInternalAccess(spec) && spec.workload_type !== WorkloadTypes.KNativeService,
        component: section(() => import('./DeploymentSpecificationServicePortsSection.vue')),
    },
    {
        key: 'service-annotations',
        title: 'Service Annotations',
        icon: 'fa fa-tags',
        group: 'Network',
        isShown: spec => hasInternalAccess(spec) && spec.workload_type == WorkloadTypes.Deployment,
        component: section(() => import('./DeploymentSpecificationServiceAnnotationsSection.vue')),
    },

    {
        key: 'cron-jobs',
        title: 'Cron Jobs',
        icon: 'fa fa-clock',
        group: 'Cronjob',
        isShown: () => true,
        isEnabled: spec => spec.enable_cronjob ?? false,
        disabledHint: 'Enable Cronjob under General',
        component: section(() => import('./DeploymentSpecificationCronJobsSection.vue')),
    },

    {
        key: 'post-migration-commands',
        title: 'Post Migration Commands',
        icon: 'fa fa-terminal',
        group: 'Migration',
        isShown: spec => spec.enable_database ?? false,
        component: section(() => import('./DeploymentSpecificationPostCommandsSection.vue')),
    },

    {
        key: 'cluster-role-rules',
        title: 'Cluster Role Rules',
        icon: 'fa fa-shield',
        group: 'RBAC',
        isShown: spec => spec.enable_rbac ?? false,
        component: section(() => import('./DeploymentSpecificationClusterRoleRulesSection.vue')),
    },
    {
        key: 'role-rules',
        title: 'Role Rules',
        icon: 'fa fa-shield',
        group: 'RBAC',
        isShown: spec => spec.enable_rbac ?? false,
        component: section(() => import('./DeploymentSpecificationRoleRulesSection.vue')),
    },

    {
        key: 'post-update-actions',
        title: 'Post Update Actions',
        icon: 'fa fa-terminal',
        group: 'Update',
        isShown: spec => !isCustomResource(spec),
        isEnabled: spec => spec.container_image?.version_control_enabled ?? false,
        disabledHint: 'Set up version control on the container image to enable post update actions',
        component: section(() => import('./DeploymentSpecificationPostUpdateActionsSection.vue')),
    },

    {
        key: 'quick-commands',
        title: 'Quick Commands',
        icon: 'fa fa-terminal',
        group: 'Other',
        isShown: spec => !isCustomResource(spec),
        component: section(() => import('./DeploymentSpecificationQuickCommandsSection.vue')),
    },
    {
        key: 'labels',
        title: 'Labels',
        icon: 'fa fa-tags',
        group: 'Other',
        isShown: () => true,
        component: section(() => import('./DeploymentSpecificationLabelsSection.vue')),
    },
    {
        key: 'volumes',
        title: 'Volumes',
        icon: 'fa fa-hard-drive',
        group: 'Other',
        isShown: spec => spec.enable_volumes ?? false,
        component: section(() => import('./DeploymentSpecificationVolumesSection.vue')),
    },
];

/** The heading a group shows, which for some carries the specification's own type. */
export function groupTitle(group: string, spec: DeploymentSpecification): string {
    switch (group) {
        case 'Workload':
            return `Workload - ${spec.workload_type}`;
        case 'Network':
            return `Network - ${spec.network_type}`;
        default:
            return group;
    }
}

/** The shown sections, grouped, in order. */
export function groupedSections(spec: DeploymentSpecification): DetailSectionGroup<DeploymentSpecification>[] {
    return groupSections(deploymentSpecificationSections, spec);
}
