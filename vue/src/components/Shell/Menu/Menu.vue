<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref} from 'vue'
import {useRouter} from "vue-router";
import {RbacPermissions} from "@/constants";
import AuthService from "@/services/AuthService";
import {Api} from "@/core/services/Deploy/Api";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";

interface MenuCategory {
    identifier: string;
    name: string;
    icon: string;
    items: MenuItem[];
    active?: boolean;
    badge?: number;
}

interface MenuItem {
    title: string;
    url: string;
    active?: boolean;
    permissions: string[];
    badge?: number;
}

const router = useRouter();

const categories = ref<MenuCategory[]>([
    {
        identifier: 'users',
        name: 'Users',
        icon: 'fa fa-users',
        items: [
            {
                title: 'All',
                url: '/users',
                permissions: [
                    RbacPermissions.Developer,
                    RbacPermissions.Users.List,
                ],
            },
        ],
    },
    {
        identifier: 'sites',
        name: 'Workspaces',
        icon: 'fa fa-window-maximize',
        items: [
            {
                title: 'All',
                url: '/workspaces',
                permissions: [
                    RbacPermissions.Developer,
                    RbacPermissions.Workspaces.List,
                ],
            },
        ],
    },
    {
        identifier: 'migration-jobs',
        name: 'Migration Jobs',
        icon: 'fa fa-truck-arrow-right',
        items: [
            {
                title: 'All',
                url: '/migration-jobs',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
        ],
    },
    {
        identifier: 'auto-updates',
        name: 'Updates',
        icon: 'fa fa-bell',
        items: [
            {
                title: 'All',
                url: '/auto-updates',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
        ],
    },
    {
        identifier: 'setup',
        name: 'Setup',
        icon: 'fa fa-gear',
        items: [
            {
                title: 'System',
                url: '/setup/system',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Deployments',
                url: '/setup/deployments',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Domains',
                url: '/setup/domains',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Email Services',
                url: '/setup/email-services',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Database Services',
                url: '/setup/database-services',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Container Images',
                url: '/setup/container-images',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Deployment Specifications',
                url: '/setup/deployment-specifications',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Workspace Templates',
                url: '/setup/deployment-packages',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
        ],
    },
    {
        identifier: 'integrations',
        name: 'Integrations',
        icon: 'fa fa-rocket',
        items: [
            {
                title: 'OAuth Clients',
                url: '/integrations/oauth-clients',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Webhooks',
                url: '/integrations/webhooks',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
            {
                title: 'Podio Integrations',
                url: '/integrations/podio-integrations',
                permissions: [
                    RbacPermissions.Developer,
                ],
            },
        ],
    }
]);

const autoUpdatesBadgeWampSubscription1 = ref<WampSubscription>();
const autoUpdatesBadgeWampSubscription2 = ref<WampSubscription>();
const autoUpdatesBadgeWampSubscription3 = ref<WampSubscription>();

function onLogoClicked(event: Event) {
    router.push({
        name: 'Dashboard',
    }).catch(_ => {
    });
}

function onListGroupClicked(category: MenuCategory) {
    if (category.items.length == 1) {
        router.push(category.items[0].url).catch((e: any) => {
        });
    }
}

onMounted(() => {
    const userPermissions: string[] = AuthService.currentAuthUser?.allPermissions ?? [];
    categories.value = categories.value.filter(category => {
        category.items = category.items.filter(item => {
            return item.permissions.some(permission => userPermissions.includes(permission));
        });
        return category.items.length > 0;
    });

    autoUpdatesBadgeWampSubscription1.value = WampService.subscribe(
        Events.AutoUpdate_Created(),
        data => countAutoUpdates()
    );
    autoUpdatesBadgeWampSubscription2.value = WampService.subscribe(
        Events.AutoUpdate_Approved(),
        data => countAutoUpdates()
    );
    autoUpdatesBadgeWampSubscription3.value = WampService.subscribe(
        Events.AutoUpdate_Deleted(),
        data => countAutoUpdates()
    );
    countAutoUpdates();
});

onUnmounted(() => {
    autoUpdatesBadgeWampSubscription1.value?.unsubscribe();
    autoUpdatesBadgeWampSubscription2.value?.unsubscribe();
});

function countAutoUpdates() {
    Api.autoUpdates().get()
        .where('is_approved', false)
        .count(value => {
            const menuItem = categories.value.find(category => category.identifier == 'auto-updates');
            if (menuItem) {
                menuItem.badge = value;
            }
        });
}

</script>

<template>
    <v-navigation-drawer
        :app="true"
        color="surface"
        :expand-on-hover="true"
        :mini-variant="true"
        :right="false"
        :permanent="true"
        :mini-variant-width="80"
        flat
        elevation="0"
    >
        <v-list
            dense nav
            class="py-1"
        >
            <v-list-item @click="onLogoClicked" class="d-flex align-items-start">
                <v-list-item-title class="title" style="line-height: 1.4rem">
                    <div class="logo">KSO</div>
                </v-list-item-title>
            </v-list-item>

            <v-list-item
                style="min-height: 44px; align-items: start;"
                class="category"
                v-for="(category, index) in categories" :key="index">
                <v-list-item
                    @click="onListGroupClicked(category)"
                    v-if="category.items.length === 1"
                    :to="category.items[0].url" link>
                    <template v-slot:prepend>
                        <v-icon size="16">{{ category.icon }}</v-icon>
                    </template>
                    <v-list-item-title>{{ category.name }}</v-list-item-title>

                    <template v-slot:append>
                        <v-badge
                            v-if="category.badge"
                            inline
                            color="secondary"
                            :content="category.badge"
                        />
                    </template>
                </v-list-item>

                <v-list-group
                    v-if="category.items.length > 1"
                    @click="onListGroupClicked(category)"
                    :value="category.active">

                    <template v-slot:activator="{ props }">
                        <v-list-item v-bind="props">
                            <template v-slot:prepend>
                                <v-icon size="16">{{ category.icon }}</v-icon>
                            </template>
                            <v-list-item-title>{{ category.name }}</v-list-item-title>
                        </v-list-item>
                    </template>

                    <div
                        v-for="item in category.items" :key="item.title" class="category-item">
                        <v-list-item :to="item.url" link>
                            <v-list-item-title>{{ item.title }}</v-list-item-title>

                            <template v-slot:append>
                                <v-badge
                                    v-if="item.badge"
                                    inline
                                    color="secondary"
                                    :content="item.badge"
                                />
                            </template>
                        </v-list-item>
                    </div>

                </v-list-group>
            </v-list-item>

        </v-list>
    </v-navigation-drawer>
</template>

<style scoped lang="scss">

.logo {
    font-size: 18px;
    color: #1a3b46;
    font-family: "Roboto", sans-serif;
    padding: 8px;
}

:deep(.v-list-item__prepend > .v-icon) {
    margin-inline-end: 5px;
}

:deep(.v-navigation-drawer) {
    box-shadow: none !important;
}

.v-list-group--open {
    background: transparent;

}

.v-list {
    padding: 0;


    :deep(.fas) {
        font-size: 12px;
    }

    :deep(.fa-chevron-down:before) {
        content: "\f13a";
    }

    :deep(.fa-chevron-up:before) {
        content: "\f139";
    }
}

.v-list-item {
    margin: 0 !important;
    border-radius: 0 !important;
    padding: 0;
    border-bottom: none !important;


    .v-list-item--link,
    > .v-list-item,
    > .v-list-group {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
}

.v-list-item--active {
    border-left: 4px solid #2e92a3;
    color: #2e92a3;
    color: var(--v-primary-base);
    color: var(--v-secondary-base);
}

.v-list-item__content:has(v-list-group--open) {

}

.v-list-group__items .v-list-item {
    padding-inline-start: calc(0px + var(--indent-padding)) !important;
    padding-inline-start: 36px !important;
}

.v-list-item--density-default.v-list-item--one-line {
    min-height: 48px !important;
    padding: 0;
}


</style>
