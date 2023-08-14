import UserListPage from "@/components/Pages/Users/UserListPage.vue";

export default ([
    {
        path: '/users',
        name: 'Users',
        component: UserListPage,
        meta: {
            title: 'Users',
        }
    },
    {
        path: '/users/:id',
        name: 'UserById',
        component: UserListPage,
        meta: {
            title: 'Users',
        }
    },
]);
