import WorkspaceOverviewPage from "@/components/Pages/Workspaces/WorkspaceOverviewPage.vue";
import WorkspaceListPage from "@/components/Pages/Workspaces/WorkspaceListPage.vue";
import WorkspacePage from "@/components/Pages/Workspaces/WorkspacePage.vue";

export default ([
    {
        path: '/workspaces',
        name: 'WorkspacesOverview',
        component: WorkspaceOverviewPage,
        meta: {
            title: 'Workspaces',
        }
    },
    {
        path: '/workspaces/all',
        name: 'Workspaces',
        component: WorkspaceListPage,
        meta: {
            title: 'Workspaces',
        }
    },
    {
        // A project's id, or `none` for the workspaces in no project.
        path: '/workspaces/projects/:project',
        name: 'WorkspacesByProject',
        component: WorkspaceListPage,
        meta: {
            title: 'Workspaces',
        }
    },
    {
        // :section is a key from workspaceSections, left out for Overview.
        path: '/workspaces/:id(\\d+)/:section?',
        name: 'WorkspaceById',
        component: WorkspacePage,
        meta: {
            title: 'Workspace',
        }
    },
]);
