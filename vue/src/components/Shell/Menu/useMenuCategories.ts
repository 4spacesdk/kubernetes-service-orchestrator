import { onMounted, onUnmounted, ref } from "vue";
import { useRoute } from "vue-router";
import { HealthStatusTypes } from "@/constants";
import AuthService from "@/services/AuthService";
import { Api } from "@/core/services/Deploy/Api";
import { PushSubscription } from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import { visibleMenuCategories, type MenuCategory } from "@/components/Shell/Menu/menuCategories";

/**
 * The menu's categories as the signed-in user sees them, with their badges kept up to date:
 * the side menu on a screen, the bottom menu on a phone.
 */
export function useMenuCategories() {
    const route = useRoute();

    const categories = ref<MenuCategory[]>([]);

    const autoUpdatesBadgePushSubscription1 = ref<PushSubscription>();
    const autoUpdatesBadgePushSubscription2 = ref<PushSubscription>();
    const autoUpdatesBadgePushSubscription3 = ref<PushSubscription>();
    const healthBadgePushSubscription = ref<PushSubscription>();

    function isCategoryActive(category: MenuCategory): boolean {
        return route.path == category.url
            || category.items.some((item) => isItemActive(item.url));
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

    return { categories, isCategoryActive, isItemActive, badgeOf, categoryUrl };
}
