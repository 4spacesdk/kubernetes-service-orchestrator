import ContainerImageListPage from "@/components/Pages/Setup/ContainerImages/ContainerImageListPage.vue";

export default ([
    {
        path: '/setup/container-images',
        name: 'ContainerImages',
        component: ContainerImageListPage,
        meta: {
            title: 'ContainerImages',
        }
    },
    {
        path: '/setup/container-images/:id',
        name: 'ContainerImagesById',
        component: ContainerImageListPage,
        meta: {
            title: 'ContainerImages',
        }
    },
]);
