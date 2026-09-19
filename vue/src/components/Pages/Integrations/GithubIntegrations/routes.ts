import GithubIntegrationListPage from "@/components/Pages/Integrations/GithubIntegrations/GithubIntegrationListPage.vue";

export default ([
    {
        path: '/integrations/github-integrations',
        name: 'GithubIntegrations',
        component: GithubIntegrationListPage,
        meta: {
            title: 'GitHub Integrations',
        }
    },
    {
        path: '/integrations/github-integrations/:id',
        name: 'GithubIntegrationById',
        component: GithubIntegrationListPage,
        meta: {
            title: 'GitHub Integrations',
        }
    },
]);
