import DeploymentListPage from "@/components/Pages/Setup/Deployments/DeploymentListPage.vue";
import DeploymentPage from "@/components/Pages/Setup/Deployments/DeploymentPage.vue";

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
        // :section is a key from deploymentSections, left out for Overview.
        path: '/setup/deployments/:id/:section?',
        name: 'DeploymentById',
        component: DeploymentPage,
        meta: {
            title: 'Deployment',
        }
    },
]);
