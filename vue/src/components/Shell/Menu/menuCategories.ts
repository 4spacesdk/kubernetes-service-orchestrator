import { RbacPermissions } from "@/constants";
import { System } from "@/core/services/Deploy/models";

export interface MenuCategory {
    identifier: string;
    name: string;
    icon: string;
    /** An overview of the category's pages, for a category of more than one. */
    url?: string;
    items: MenuItem[];
    active?: boolean;
    badge?: number;
    /** Secondary unless something is wrong - the Degraded counts are red. */
    badgeColor?: string;
}

export interface MenuItem {
    title: string;
    url: string;
    /** Shown on the category's overview page. */
    icon?: string;
    description?: string;
    active?: boolean;
    permissions: string[];
    badge?: number;
    badgeColor?: string;
}

/**
 * The global menu, and the overview pages of its categories. A new list each call, as the
 * menu fills in badges on its own.
 */
export function createMenuCategories(): MenuCategory[] {
    return [
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
            url: "/setup",
            items: [
                {
                    title: "System",
                    url: "/setup/system",
                    icon: "fa fa-sliders",
                    description: "Hosting provider and the network types the cluster supports",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Gateways",
                    url: "/setup/gateways",
                    icon: "fa fa-door-open",
                    description: "Gateway API gateways that routes attach to",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Deployments",
                    url: "/setup/deployments",
                    icon: "fa fa-cubes",
                    description: "Everything deployed, its status and health",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Domains",
                    url: "/setup/domains",
                    icon: "fa fa-globe",
                    description: "Domains and their certificates",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Email Services",
                    url: "/setup/email-services",
                    icon: "fa fa-envelope",
                    description: "Mail servers deployments can send through",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Database Services",
                    url: "/setup/database-services",
                    icon: "fa fa-database",
                    description: "Database servers deployments get a database on",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Container Images",
                    url: "/setup/container-images",
                    icon: "fa fa-box",
                    description: "Images, their tags, scans and version control",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Deployment Specifications",
                    url: "/setup/deployment-specifications",
                    icon: "fa fa-file-lines",
                    description: "What a deployment is made of: image, network, variables and more",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Workspace Templates",
                    url: "/setup/workspace-templates",
                    icon: "fa fa-layer-group",
                    description: "The specifications a new workspace starts with",
                    permissions: [RbacPermissions.Developer],
                },
            ],
        },
        {
            identifier: "integrations",
            name: "Integrations",
            icon: "fa fa-rocket",
            url: "/integrations",
            items: [
                {
                    title: "OAuth Clients",
                    url: "/integrations/oauth-clients",
                    icon: "fa fa-key",
                    description: "Clients that may call kso's API",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Webhooks",
                    url: "/integrations/webhooks",
                    icon: "fa fa-bolt",
                    description: "Calls kso makes when something happens",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Podio Integrations",
                    url: "/integrations/podio-integrations",
                    icon: "fa fa-plug",
                    description: "Connections to Podio",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "GitHub Integrations",
                    url: "/integrations/github-integrations",
                    icon: "fa-brands fa-github",
                    description: "Connections to GitHub, for version control on images",
                    permissions: [RbacPermissions.Developer],
                },
                {
                    title: "Container Registries",
                    url: "/integrations/container-registries",
                    icon: "fa fa-warehouse",
                    description: "Registries images are pulled and imported from",
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
    ];
}

/** The categories and pages these permissions reach. A category with none left is left out. */
export function visibleMenuCategories(userPermissions: string[]): MenuCategory[] {
    return createMenuCategories().filter((category) => {
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
}
