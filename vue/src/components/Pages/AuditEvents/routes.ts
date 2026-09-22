import AuditEventListPage from "@/components/Pages/AuditEvents/AuditEventListPage.vue";

export default ([
    {
        path: '/audit',
        name: 'AuditEvents',
        component: AuditEventListPage,
        meta: {
            title: 'Audit Trail',
        }
    },
]);
