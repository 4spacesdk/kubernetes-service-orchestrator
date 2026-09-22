import DeploymentSpecificationListPage from "@/components/Pages/Setup/DeploymentSpecifications/DeploymentSpecificationListPage.vue";
import DeploymentSpecificationPage from "@/components/Pages/Setup/DeploymentSpecifications/DeploymentSpecificationPage.vue";

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
        // :section is a key from deploymentSpecificationSections, left out for General.
        path: '/setup/deployment-specifications/:id/:section?',
        name: 'DeploymentSpecificationById',
        component: DeploymentSpecificationPage,
        meta: {
            title: 'DeploymentSpecification',
        }
    },
]);
