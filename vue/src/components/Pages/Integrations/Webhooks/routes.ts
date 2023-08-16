import WebhookListPage from "@/components/Pages/Integrations/Webhooks/WebhookListPage.vue";

export default ([
    {
        path: '/integrations/webhooks',
        name: 'Webhooks',
        component: WebhookListPage,
        meta: {
            title: 'Webhooks',
        }
    },
    {
        path: '/integrations/webhooks/:id',
        name: 'WebhookById',
        component: WebhookListPage,
        meta: {
            title: 'Webhooks',
        }
    },
]);
