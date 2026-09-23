import ProjectListPage from "@/components/Pages/Setup/Projects/ProjectListPage.vue";

export default ([
    {
        path: '/setup/projects',
        name: 'Projects',
        component: ProjectListPage,
        meta: {
            title: 'Projects',
        }
    },
    {
        path: '/setup/projects/:id',
        name: 'ProjectById',
        component: ProjectListPage,
        meta: {
            title: 'Projects',
        }
    },
]);
