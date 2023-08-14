import EmailServiceListPage from "@/components/Pages/Setup/EmailServices/EmailServiceListPage.vue";

export default ([
    {
        path: '/setup/email-services',
        name: 'EmailServices',
        component: EmailServiceListPage,
        meta: {
            title: 'Email Services',
        }
    },
    {
        path: '/setup/email-services/:id',
        name: 'EmailServiceById',
        component: EmailServiceListPage,
        meta: {
            title: 'Email Services',
        }
    },
]);
