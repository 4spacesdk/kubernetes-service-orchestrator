import WorkspaceListPage from "@/components/Pages/Workspaces/WorkspaceListPage.vue";
import WorkspaceRequestSupportLoginPage from "@/components/Pages/Workspaces/WorkspaceRequestSupportLoginPage.vue";

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
        path: '/workspaces/:id/request-support-login',
        name: 'WorkspaceRequestSupportLogin',
        component: WorkspaceRequestSupportLoginPage,
        meta: {
            title: 'Workspaces',
        }
    },
]);
