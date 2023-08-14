import MigrationJobListPage from "@/components/Pages/MigrationJobs/MigrationJobListPage.vue";

export default ([
    {
        path: '/migration-jobs',
        name: 'MigrationJobs',
        component: MigrationJobListPage,
        meta: {
            title: 'Migration Jobs',
        }
    },
]);
