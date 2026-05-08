import GatewayListPage from "@/components/Pages/Setup/Gateways/GatewayListPage.vue";

export default ([
    {
        path: '/setup/gateways',
        name: 'Gateways',
        component: GatewayListPage,
        meta: {
            title: 'Gateways',
        }
    },
    {
        path: '/setup/gateways/:id',
        name: 'GatewaysById',
        component: GatewayListPage,
        meta: {
            title: 'Gateways',
        }
    },
]);
