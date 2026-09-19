import ContainerRegistryListPage from "@/components/Pages/Integrations/ContainerRegistries/ContainerRegistryListPage.vue";

export default ([
    {
        path: '/integrations/container-registries',
        name: 'ContainerRegistries',
        component: ContainerRegistryListPage,
        meta: {
            title: 'Container Registries',
        }
    },
    {
        path: '/integrations/container-registries/:id',
        name: 'ContainerRegistryById',
        component: ContainerRegistryListPage,
        meta: {
            title: 'Container Registries',
        }
    },
]);
