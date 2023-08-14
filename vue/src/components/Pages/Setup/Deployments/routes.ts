import DeploymentListPage from "@/components/Pages/Setup/Deployments/DeploymentListPage.vue";

export default ([
    {
        path: '/setup/deployments',
        name: 'Deployments',
        component: DeploymentListPage,
        meta: {
            title: 'Deployments',
        }
    },
    {
        path: '/setup/deployments/:id',
        name: 'DeploymentById',
        component: DeploymentListPage,
        meta: {
            title: 'Deployments',
        }
    },
]);
