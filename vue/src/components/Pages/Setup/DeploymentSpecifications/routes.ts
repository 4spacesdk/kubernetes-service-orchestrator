import DeploymentSpecificationListPage from "@/components/Pages/Setup/DeploymentSpecifications/DeploymentSpecificationListPage.vue";

export default ([
    {
        path: '/setup/deployment-specifications',
        name: 'DeploymentSpecifications',
        component: DeploymentSpecificationListPage,
        meta: {
            title: 'DeploymentSpecifications',
        }
    },
    {
        path: '/setup/deployment-specifications/:id',
        name: 'DeploymentSpecificationById',
        component: DeploymentSpecificationListPage,
        meta: {
            title: 'DeploymentSpecification',
        }
    },
]);
