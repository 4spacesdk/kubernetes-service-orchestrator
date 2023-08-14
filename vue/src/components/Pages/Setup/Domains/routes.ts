import DomainListPage from "@/components/Pages/Setup/Domains/DomainListPage.vue";

export default ([
    {
        path: '/setup/domains',
        name: 'Domains',
        component: DomainListPage,
        meta: {
            title: 'Domains',
        }
    },
    {
        path: '/setup/domains/:id',
        name: 'DomainsById',
        component: DomainListPage,
        meta: {
            title: 'Domains',
        }
    },
]);
