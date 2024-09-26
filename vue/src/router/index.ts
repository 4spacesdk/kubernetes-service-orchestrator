import {createRouter, createWebHistory} from 'vue-router'
import DashboardPage from '@/components/Pages/DashboardPage.vue';
import LoginPage from '@/components/Pages/LoginPage.vue';
import AuthService from "@/services/AuthService";
import UserRoutes from '@/components/Pages/Users/routes';
import WorkspaceRoutes from '@/components/Pages/Workspaces/routes';
import SetupRoutes from '@/components/Pages/Setup/routes';
import MigrationJobsRoutes from '@/components/Pages/MigrationJobs/routes';
import IntegrationRoutes from '@/components/Pages/Integrations/routes';
import AutoUpdatesRoutes from '@/components/Pages/AutoUpdates/routes';

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        { 
            path: '/',
            name: 'Dashboard',
            component: DashboardPage,
            meta: {
                title: 'Dashboard',
            }
        },

        ...UserRoutes,
        ...WorkspaceRoutes,
        ...SetupRoutes,
        ...IntegrationRoutes,
        ...MigrationJobsRoutes,
        ...AutoUpdatesRoutes,

        {
            path: "/login",
            name: "Login",
            component: LoginPage,
            meta: {
                public: true,
                onlyWhenLoggedOut: true
            }
        }
    ]
})

router.beforeEach((to, from, next) => {
    const isPublic = to.matched.some(record => record.meta.public);
    const onlyWhenLoggedOut = to.matched.some(
        record => record.meta.onlyWhenLoggedOut
    );
    const loggedIn = AuthService.isLoggedIn();

    if (!isPublic && !loggedIn) {
        return next({
            path: "/login",
            query: {redirect: to.fullPath} // Store the full path to redirect the user to after login
        });
    }

    // Do not allow user to visit login page or register page if they are logged in
    if (loggedIn && onlyWhenLoggedOut) {
        return next("/");
    }

    // Set page title
    document.title = (to.meta as any)?.title ?? 'Deploy';

    next();
});

export default router
