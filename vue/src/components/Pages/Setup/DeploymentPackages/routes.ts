import DeploymentPackageListPage from "@/components/Pages/Setup/DeploymentPackages/DeploymentPackageListPage.vue";

export default ([
    {
        path: '/setup/deployment-packages',
        name: 'DeploymentPackages',
        component: DeploymentPackageListPage,
        meta: {
            title: 'DeploymentPackages',
        }
    },
    {
        path: '/setup/deployment-packages/:id',
        name: 'DeploymentPackageById',
        component: DeploymentPackageListPage,
        meta: {
            title: 'DeploymentPackages',
        }
    },
]);
