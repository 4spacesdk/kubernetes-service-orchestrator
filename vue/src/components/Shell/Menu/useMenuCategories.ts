import { onMounted, ref, watchEffect } from "vue";
import { useRoute } from "vue-router";
import AuthService from "@/services/AuthService";
import { visibleMenuCategories, type MenuCategory } from "@/components/Shell/Menu/menuCategories";
import { categoryBadge, itemBadge, useMenuBadges } from "@/components/Shell/Menu/menuBadges";

/**
 * The menu's categories as the signed-in user sees them, with their badges kept up to date:
 * the side menu on a screen, the bottom menu on a phone.
 */
export function useMenuCategories() {
    const route = useRoute();

    const categories = ref<MenuCategory[]>([]);

    useMenuBadges();

    // The shared numbers onto the categories and pages that show them.
    watchEffect(() => {
        for (const category of categories.value) {
            const badge = categoryBadge(category.identifier);
            category.badge = badge?.count;
            category.badgeColor = badge?.color;
            for (const item of category.items) {
                const badge = itemBadge(item.url);
                item.badge = badge?.count;
                item.badgeColor = badge?.color;
            }
        }
    });

    function isCategoryActive(category: MenuCategory): boolean {
        return (category.url !== undefined && isItemActive(category.url))
            || category.items.some((item) => isItemActive(item.url));
    }

    /** A category with an overview lists its pages under it; one without is its one page. */
    function hasSubmenu(category: MenuCategory): boolean {
        return category.url !== undefined && category.items.length > 0;
    }

    function isItemActive(url: string): boolean {
        return route.path == url || route.path.startsWith(url + "/");
    }

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

    /** Where the category's own icon goes: its overview, or its one page. */
    function categoryUrl(category: MenuCategory): string | undefined {
        return category.url ?? (category.items.length === 1 ? category.items[0].url : undefined);
    }

    onMounted(() => {
        categories.value = visibleMenuCategories(AuthService.currentAuthUser?.allPermissions ?? []);

        // Workspaces lists the user's own projects; every project is on its overview.
        const workspaces = categories.value.find((category) => category.identifier == "sites");
        if (workspaces) {
            workspaces.items = [...(AuthService.currentAuthUser?.projects ?? [])]
                .sort((a, b) => (a.name ?? "").localeCompare(b.name ?? ""))
                .map((project) => ({
                    title: project.name ?? "",
                    url: `/workspaces/projects/${project.id}`,
                    icon: "fa fa-folder",
                    permissions: [],
                }));
        }
    });

    return { categories, isCategoryActive, isItemActive, hasSubmenu, badgeOf, categoryUrl };
}
