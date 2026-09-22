import WorkspaceTemplateListPage from "@/components/Pages/Setup/WorkspaceTemplates/WorkspaceTemplateListPage.vue";

export default ([
    {
        path: '/setup/workspace-templates',
        name: 'WorkspaceTemplates',
        component: WorkspaceTemplateListPage,
        meta: {
            title: 'WorkspaceTemplates',
        }
    },
    {
        path: '/setup/workspace-templates/:id',
        name: 'WorkspaceTemplateById',
        component: WorkspaceTemplateListPage,
        meta: {
            title: 'WorkspaceTemplates',
        }
    },
]);
