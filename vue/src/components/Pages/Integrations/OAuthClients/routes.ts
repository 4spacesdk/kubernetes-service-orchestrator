import OAuthClientListPage from "@/components/Pages/Integrations/OAuthClients/OAuthClientListPage.vue";

export default ([
    {
        path: '/integrations/oauth-clients',
        name: 'OAuthClients',
        component: OAuthClientListPage,
        meta: {
            title: 'OAuth Clients',
        }
    },
    {
        path: '/integrations/oauth-clients/:id',
        name: 'OAuthClientById',
        component: OAuthClientListPage,
        meta: {
            title: 'OAuth Clients',
        }
    },
]);
