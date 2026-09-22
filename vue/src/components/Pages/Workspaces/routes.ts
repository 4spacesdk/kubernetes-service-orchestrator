import WorkspaceListPage from "@/components/Pages/Workspaces/WorkspaceListPage.vue";
import WorkspacePage from "@/components/Pages/Workspaces/WorkspacePage.vue";

export default ([
    {
        path: '/workspaces',
        name: 'Workspaces',
        component: WorkspaceListPage,
        meta: {
            title: 'Workspaces',
        }
    },
    {
        path: '/workspaces/:id',
        name: 'WorkspaceById',
        component: WorkspacePage,
        meta: {
            title: 'Workspace',
        }
    },
]);
