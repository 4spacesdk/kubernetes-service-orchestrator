import ContainerRegistryListPage from "@/components/Pages/Setup/ContainerRegistries/ContainerRegistryListPage.vue";

export default ([
    {
        path: '/setup/container-registries',
        name: 'ContainerRegistries',
        component: ContainerRegistryListPage,
        meta: {
            title: 'Container Registries',
        }
    },
    {
        path: '/setup/container-registries/:id',
        name: 'ContainerRegistryById',
        component: ContainerRegistryListPage,
        meta: {
            title: 'Container Registries',
        }
    },
]);
