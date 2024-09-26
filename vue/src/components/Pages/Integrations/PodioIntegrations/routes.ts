import PodioIntegrationListPage from "@/components/Pages/Integrations/PodioIntegrations/PodioIntegrationListPage.vue";

export default ([
    {
        path: '/integrations/podio-integrations',
        name: 'PodioIntegrations',
        component: PodioIntegrationListPage,
        meta: {
            title: 'Podio Integrations',
        }
    },
    {
        path: '/integrations/podio-integrations/:id',
        name: 'PodioIntegrationById',
        component: PodioIntegrationListPage,
        meta: {
            title: 'Podio Integrations',
        }
    },
]);
