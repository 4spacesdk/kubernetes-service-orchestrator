import {defineAsyncComponent} from "vue";
import {type DetailSection} from "@/components/Modules/Common/DetailPage/detailSections";
import type {Workspace} from "@/core/services/Deploy/models";
import {DeploymentStatusTypes, RbacPermissions} from "@/constants";
import AuthService from "@/services/AuthService";

export type WorkspaceSection = DetailSection<Workspace>;

const section = (loader: () => Promise<any>) => defineAsyncComponent(loader);

const can = (permission: string) => AuthService.currentAuthUser?.hasPermission(permission) ?? false;
const isDeveloper = () => can(RbacPermissions.Developer);
const canUpdate = () => can(RbacPermissions.Workspaces.Update);

/**
 * Everything a workspace has, in the order the side menu shows it. The settings menu in the
 * list reads the same.
 */
export const workspaceSections: WorkspaceSection[] = [
    {
        key: '',
        title: 'Overview',
        icon: 'fa fa-gauge',
        group: '',
        isShown: () => true,
        component: section(() => import('./WorkspaceOverviewSection.vue')),
    },
    {
        key: 'deployments',
        title: 'Deployments',
        icon: 'fa fa-cubes',
        group: '',
        isShown: () => true,
        component: section(() => import('./WorkspaceDeploymentsSection.vue')),
    },
    {
        key: 'updates',
        title: 'Updates',
        icon: 'fa fa-bell',
        group: '',
        isShown: isDeveloper,
        component: section(() => import('./WorkspaceAutoUpdatesSection.vue')),
    },
    {
        key: 'logs',
        title: 'Kubernetes Logs',
        icon: 'fa fa-rectangle-list',
        group: '',
        isShown: isDeveloper,
        component: section(() => import('./WorkspaceLogsSection.vue')),
    },
    {
        key: 'migration-jobs',
        title: 'Migration Jobs',
        icon: 'fa fa-truck-arrow-right',
        group: '',
        isShown: isDeveloper,
        component: section(() => import('./WorkspaceMigrationJobsSection.vue')),
    },
    {
        key: 'history',
        title: 'History',
        icon: 'fa fa-clock-rotate-left',
        group: '',
        isShown: isDeveloper,
        component: section(() => import('./WorkspaceHistorySection.vue')),
    },

    {
        key: 'project',
        title: 'Project',
        icon: 'fa fa-folder',
        group: 'Settings',
        isShown: canUpdate,
        component: section(() => import('./WorkspaceProjectSection.vue')),
    },
    {
        key: 'name',
        title: 'Name',
        icon: 'fa fa-font',
        group: 'Settings',
        isShown: canUpdate,
        component: section(() => import('./WorkspaceNameSection.vue')),
    },
    {
        key: 'domain',
        title: 'Domain',
        icon: 'fa fa-globe',
        group: 'Settings',
        isShown: canUpdate,
        component: section(() => import('./WorkspaceDomainSection.vue')),
    },
    {
        key: 'email-service',
        title: 'Email Service',
        icon: 'fa fa-envelope',
        group: 'Settings',
        isShown: canUpdate,
        component: section(() => import('./WorkspaceEmailServiceSection.vue')),
    },
    {
        key: 'database-service',
        title: 'Database Service',
        icon: 'fa fa-database',
        group: 'Settings',
        isShown: canUpdate,
        // The database a workspace uses can only change before anything of it has been deployed.
        isEnabled: workspace => !(workspace.deployments ?? [])
            .some(deployment => deployment.status !== DeploymentStatusTypes.Draft),
        disabledHint: 'Only while none of its deployments has been deployed',
        component: section(() => import('./WorkspaceDatabaseServiceSection.vue')),
    },
    {
        key: 'labels',
        title: 'Labels',
        icon: 'fa fa-tags',
        group: 'Settings',
        isShown: canUpdate,
        component: section(() => import('./WorkspaceLabelsSection.vue')),
    },

    {
        key: 'pause',
        title: 'Pause',
        icon: 'fa fa-pause',
        group: 'Advanced',
        isShown: isDeveloper,
        component: section(() => import('./WorkspacePauseSection.vue')),
    },
    {
        key: 'terminate',
        title: 'Terminate',
        icon: 'fa fa-skull',
        group: 'Advanced',
        isShown: isDeveloper,
        component: section(() => import('./WorkspaceTerminateSection.vue')),
    },
    {
        key: 'delete',
        title: 'Delete',
        icon: 'fa fa-trash',
        group: 'Advanced',
        isShown: () => can(RbacPermissions.Workspaces.Delete) || canUpdate(),
        component: section(() => import('./WorkspaceDeleteSection.vue')),
    },
];
