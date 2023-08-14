import DatabaseServiceListPage from "@/components/Pages/Setup/DatabaseServices/DatabaseServiceListPage.vue";

export default ([
    {
        path: '/setup/database-services',
        name: 'DatabaseServices',
        component: DatabaseServiceListPage,
        meta: {
            title: 'Database Services',
        }
    },
    {
        path: '/setup/database-services/:id',
        name: 'DatabaseServiceById',
        component: DatabaseServiceListPage,
        meta: {
            title: 'Database Services',
        }
    },
]);
