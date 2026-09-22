import CategoryOverviewPage from "@/components/Pages/Overview/CategoryOverviewPage.vue";

// A menu category of more than one page opens to its overview. `category` is its identifier
// in menuCategories.ts.
export default ([
    {
        path: '/setup',
        name: 'Setup',
        component: CategoryOverviewPage,
        meta: {
            title: 'Setup',
            category: 'setup',
        }
    },
    {
        path: '/integrations',
        name: 'Integrations',
        component: CategoryOverviewPage,
        meta: {
            title: 'Integrations',
            category: 'integrations',
        }
    },
]);
