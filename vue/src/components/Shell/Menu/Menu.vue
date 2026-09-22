<script setup lang="ts">
import {
    computed,
    defineComponent,
    onMounted,
    onUnmounted,
    reactive,
    ref,
    watch,
} from "vue";
import { useRouter } from "vue-router";
import { useDisplay } from "vuetify";
import { HealthStatusTypes, RbacPermissions } from "@/constants";
import AuthService from "@/services/AuthService";
import { Api } from "@/core/services/Deploy/Api";
import { System } from "@/core/services/Deploy/models";
import { PushSubscription } from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";

interface MenuCategory {
    identifier: string;
    name: string;
    icon: string;
    items: MenuItem[];
    active?: boolean;
    badge?: number;
    /** Secondary unless something is wrong - the Degraded counts are red. */
    badgeColor?: string;
}

interface MenuItem {
    title: string;
    url: string;
    active?: boolean;
    permissions: string[];
    badge?: number;
    badgeColor?: string;
}

const router = useRouter();

const categories = ref<MenuCategory[]>([
    {
        identifier: "users",
        name: "Users",
        icon: "fa fa-users",
        items: [
            {
                title: "All",
                url: "/users",
                permissions: [
                    RbacPermissions.Developer,
                    RbacPermissions.Users.List,
                ],
            },
        ],
    },
    {
        identifier: "sites",
        name: "Workspaces",
        icon: "fa fa-window-maximize",
        items: [
            {
                title: "All",
                url: "/workspaces",
                permissions: [
                    RbacPermissions.Developer,
                    RbacPermissions.Workspaces.List,
                ],
            },
        ],
    },
    {
        identifier: "migration-jobs",
        name: "Migration Jobs",
        icon: "fa fa-truck-arrow-right",
        items: [
            {
                title: "All",
                url: "/migration-jobs",
                permissions: [RbacPermissions.Developer],
            },
        ],
    },
    {
        identifier: "auto-updates",
        name: "Updates",
        icon: "fa fa-bell",
        items: [
            {
                title: "All",
                url: "/auto-updates",
                permissions: [RbacPermissions.Developer],
            },
        ],
    },
    {
        identifier: "setup",
        name: "Setup",
        icon: "fa fa-gear",
        items: [
            {
                title: "System",
                url: "/setup/system",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Gateways",
                url: "/setup/gateways",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Deployments",
                url: "/setup/deployments",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Domains",
                url: "/setup/domains",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Email Services",
                url: "/setup/email-services",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Database Services",
                url: "/setup/database-services",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Container Images",
                url: "/setup/container-images",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Deployment Specifications",
                url: "/setup/deployment-specifications",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Workspace Templates",
                url: "/setup/workspace-templates",
                permissions: [RbacPermissions.Developer],
            },
        ],
    },
    {
        identifier: "integrations",
        name: "Integrations",
        icon: "fa fa-rocket",
        items: [
            {
                title: "OAuth Clients",
                url: "/integrations/oauth-clients",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Webhooks",
                url: "/integrations/webhooks",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Podio Integrations",
                url: "/integrations/podio-integrations",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "GitHub Integrations",
                url: "/integrations/github-integrations",
                permissions: [RbacPermissions.Developer],
            },
            {
                title: "Container Registries",
                url: "/integrations/container-registries",
                permissions: [RbacPermissions.Developer],
            },
        ],
    },
    {
        identifier: "audit",
        name: "Audit Trail",
        icon: "fa fa-clipboard-list",
        items: [
            {
                title: "All",
                url: "/audit",
                permissions: [RbacPermissions.Developer],
            },
        ],
    },
]);

const autoUpdatesBadgePushSubscription1 = ref<PushSubscription>();
const autoUpdatesBadgePushSubscription2 = ref<PushSubscription>();
const autoUpdatesBadgePushSubscription3 = ref<PushSubscription>();
const healthBadgePushSubscription = ref<PushSubscription>();

/**
 * The menu stands open where there is room for it, and folds in to an 80px rail - opening over
 * the page on hover - where there is not. The lists need about 1030px, so `lg` and up (1280px)
 * keeps both; below that the menu is what gives way.
 */
const { lgAndUp } = useDisplay();
const isRail = ref(!lgAndUp.value);

watch(lgAndUp, (roomForIt) => {
    isRail.value = !roomForIt;
});

function badgeOf(category: MenuCategory): { count: number; color: string } | null {
    if (category.badge) {
        return { count: category.badge, color: category.badgeColor ?? "secondary" };
    }
    const items = category.items.filter((item) => item.badge);
    if (!items.length) {
        return null;
    }
    return {
        count: items.reduce((sum, item) => sum + (item.badge ?? 0), 0),
        color: items.find((item) => item.badgeColor)?.badgeColor ?? "secondary",
    };
}

function onLogoClicked(event: Event) {
    router
        .push({
            name: "Dashboard",
        })
        .catch((_) => {});
}

function onListGroupClicked(category: MenuCategory) {
    if (category.items.length == 1) {
        router.push(category.items[0].url).catch((e: any) => {});
    }
}

onMounted(() => {
    const userPermissions: string[] =
        AuthService.currentAuthUser?.allPermissions ?? [];
    categories.value = categories.value.filter((category) => {
        category.items = category.items.filter((item) => {
            if (
                item.url === "/setup/gateways" &&
                !System.Instance?.is_network_gateway_api_supported
            ) {
                return false;
            }

            return item.permissions.some((permission) =>
                userPermissions.includes(permission)
            );
        });
        return category.items.length > 0;
    });

    autoUpdatesBadgePushSubscription1.value = PushService.subscribe(
        Events.AutoUpdate_Created(),
        (data) => countAutoUpdates()
    );
    autoUpdatesBadgePushSubscription2.value = PushService.subscribe(
        Events.AutoUpdate_Approved(),
        (data) => countAutoUpdates()
    );
    autoUpdatesBadgePushSubscription3.value = PushService.subscribe(
        Events.AutoUpdate_Deleted(),
        (data) => countAutoUpdates()
    );
    countAutoUpdates();

    healthBadgePushSubscription.value = PushService.subscribe(
        Events.Deployments_Changed_Health(),
        (data) => countDegraded()
    );
    countDegraded();
});

onUnmounted(() => {
    autoUpdatesBadgePushSubscription1.value?.unsubscribe();
    autoUpdatesBadgePushSubscription2.value?.unsubscribe();
    autoUpdatesBadgePushSubscription3.value?.unsubscribe();
    healthBadgePushSubscription.value?.unsubscribe();
});

/**
 * What is Degraded right now: workspaces on Sites, which everybody sees, and deployments on
 * Deployments under Setup. Recounted when any deployment's health changes.
 */
function countDegraded() {
    Api.workspaces()
        .get()
        .where("health", HealthStatusTypes.Degraded)
        .count((value) => {
            const category = categories.value.find((category) => category.identifier == "sites");
            if (category) {
                category.badge = value;
                category.badgeColor = "error";
            }
        });

    Api.deployments()
        .get()
        .where("health", HealthStatusTypes.Degraded)
        .count((value) => {
            const item = categories.value
                .find((category) => category.identifier == "setup")
                ?.items.find((item) => item.url == "/setup/deployments");
            if (item) {
                item.badge = value;
                item.badgeColor = "error";
            }
        });
}

function countAutoUpdates() {
    Api.autoUpdates()
        .get()
        .where("is_approved", false)
        .count((value) => {
            const menuItem = categories.value.find(
                (category) => category.identifier == "auto-updates"
            );
            if (menuItem) {
                menuItem.badge = value;
            }
        });
}
</script>

<template>
    <!-- `rail` follows the breakpoint, and the event is only read: bound with v-model, a hover
         would set it false and the layout would make room for the full width, pushing the page
         aside instead of opening over it. `mini-variant` was Vuetify 2's name for this and did
         nothing here - the menu stood open at 256px on every screen, wide or not. -->
    <v-navigation-drawer
        color="surface"
        permanent
        :rail="!lgAndUp"
        :rail-width="80"
        :expand-on-hover="!lgAndUp"
        @update:rail="isRail = $event && !lgAndUp"
        :class="{ folded: isRail }"
        flat
        elevation="0"
    >
        <v-list dense nav class="py-1">
            <v-list-item
                @click="onLogoClicked"
                class="d-flex align-items-start"
            >
                <v-list-item-title class="title" style="line-height: 1.4rem">
                    <div class="logo">KSO</div>
                </v-list-item-title>
            </v-list-item>

            <v-list-item
                style="min-height: 44px; align-items: start"
                class="category"
                v-for="(category, index) in categories"
                :key="index"
            >
                <v-list-item
                    @click="onListGroupClicked(category)"
                    v-if="category.items.length === 1"
                    :to="category.items[0].url"
                    link
                >
                    <template v-slot:prepend>
                        <v-badge
                            v-if="isRail && badgeOf(category)"
                            :color="badgeOf(category)!.color"
                            :content="badgeOf(category)!.count"
                        >
                            <v-icon size="16">{{ category.icon }}</v-icon>
                        </v-badge>
                        <v-icon v-else size="16">{{ category.icon }}</v-icon>
                    </template>
                    <v-list-item-title>{{ category.name }}</v-list-item-title>

                    <template v-slot:append>
                        <v-badge
                            v-if="category.badge"
                            inline
                            :color="category.badgeColor ?? 'secondary'"
                            :content="category.badge"
                        />
                    </template>
                </v-list-item>

                <v-list-group
                    v-if="category.items.length > 1"
                    @click="onListGroupClicked(category)"
                    :value="category.active"
                >
                    <template v-slot:activator="{ props }">
                        <v-list-item v-bind="props">
                            <template v-slot:prepend>
                                <v-badge
                                    v-if="isRail && badgeOf(category)"
                                    :color="badgeOf(category)!.color"
                                    :content="badgeOf(category)!.count"
                                >
                                    <v-icon size="16">{{ category.icon }}</v-icon>
                                </v-badge>
                                <v-icon v-else size="16">{{ category.icon }}</v-icon>
                            </template>
                            <v-list-item-title>{{
                                category.name
                            }}</v-list-item-title>
                        </v-list-item>
                    </template>

                    <div
                        v-for="item in category.items"
                        :key="item.title"
                        class="category-item"
                    >
                        <v-list-item :to="item.url" link>
                            <v-list-item-title>{{
                                item.title
                            }}</v-list-item-title>

                            <template v-slot:append>
                                <v-badge
                                    v-if="item.badge"
                                    inline
                                    :color="item.badgeColor ?? 'secondary'"
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

/* Folded, the rail is 80px of icon: an open group's items would show as a column of cut-off
   words under its icon, and each title as its first letter against the edge. */
.folded :deep(.v-list-group__items),
.folded :deep(.v-list-item__append),
.folded :deep(.v-list-item-title) {
    display: none;
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
