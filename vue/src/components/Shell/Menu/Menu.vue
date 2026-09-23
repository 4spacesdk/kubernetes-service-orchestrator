<script setup lang="ts">
import { ref, watch } from "vue";
import { useRoute } from "vue-router";
import { type MenuCategory } from "@/components/Shell/Menu/menuCategories";
import { useMenuCategories } from "@/components/Shell/Menu/useMenuCategories";

const { categories, isCategoryActive, hasSubmenu, badgeOf, categoryUrl } = useMenuCategories();

/**
 * A rail of icons, on every screen - the page keeps the width. Hovering an icon shows its name,
 * and a category's pages, beside it, over the page, so nothing moves. On a phone it is the
 * bottom menu instead - see BottomMenu.
 */

/**
 * The category whose popover is open. A click on the icon or in the popover goes somewhere,
 * and the popover goes with it - left to hover, it stayed over the page it opened.
 */
const openFlyout = ref<string | null>(null);

function setFlyout(category: MenuCategory, open: boolean) {
    if (open) {
        openFlyout.value = category.identifier;
    } else if (openFlyout.value == category.identifier) {
        openFlyout.value = null;
    }
}

const route = useRoute();
watch(() => route.path, () => {
    openFlyout.value = null;
});

/**
 * The popover beside a rail icon (48px). A category of pages opens with its name above the
 * icon and its first page level with it; one of a single page shows its name level with it.
 */
function flyoutOffset(category: MenuCategory): number[] {
    return hasSubmenu(category) ? [8, 37] : [8, -4];
}

</script>

<template>
    <v-navigation-drawer
        color="surface"
        permanent
        rail
        :rail-width="56"
        flat
        elevation="0"
    >
        <nav class="rail">
            <v-menu
                v-for="(category, index) in categories"
                :key="index"
                :model-value="openFlyout == category.identifier"
                @update:model-value="open => setFlyout(category, open)"
                open-on-hover
                open-on-focus
                :open-delay="0"
                :close-delay="120"
                location="end top"
                :offset="flyoutOffset(category)"
            >
                <template v-slot:activator="{ props }">
                    <v-btn
                        v-bind="props"
                        :to="categoryUrl(category)"
                        :active="isCategoryActive(category)"
                        :aria-label="category.name"
                        class="rail-item"
                        @click="openFlyout = null"
                        variant="text"
                        color="secondary"
                        :ripple="false"
                        icon
                    >
                        <v-badge
                            v-if="badgeOf(category)"
                            :color="badgeOf(category)!.color"
                            :content="badgeOf(category)!.count"
                        >
                            <v-icon size="16">{{ category.icon }}</v-icon>
                        </v-badge>
                        <v-icon v-else size="16">{{ category.icon }}</v-icon>
                    </v-btn>
                </template>

                <v-card class="rail-flyout" elevation="6">
                    <component
                        :is="categoryUrl(category) ? 'router-link' : 'div'"
                        :to="categoryUrl(category)"
                        class="rail-flyout-title"
                    >
                        <span>{{ category.name }}</span>
                        <v-badge
                            v-if="badgeOf(category)"
                            inline
                            :color="badgeOf(category)!.color"
                            :content="badgeOf(category)!.count"
                        />
                    </component>
                    <v-list v-if="hasSubmenu(category)" density="compact" class="rail-flyout-items">
                        <v-list-item
                            v-for="item in category.items"
                            :key="item.title"
                            :to="item.url"
                            color="secondary"
                        >
                            <template v-slot:prepend>
                                <v-icon size="14">{{ item.icon ?? category.icon }}</v-icon>
                            </template>
                            <v-list-item-title>{{ item.title }}</v-list-item-title>
                            <template v-slot:append>
                                <v-badge
                                    v-if="item.badge"
                                    inline
                                    :color="item.badgeColor ?? 'secondary'"
                                    :content="item.badge"
                                />
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card>
            </v-menu>
        </nav>
    </v-navigation-drawer>
</template>

<style scoped lang="scss">
.rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 8px 0;
}

.rail-item {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    color: rgba(var(--v-theme-on-surface), 0.6) !important;
}

/* A count on an icon, small enough to leave the icon readable. */
.rail-item :deep(.v-badge__badge) {
    height: 15px;
    min-width: 15px;
    padding: 0 4px;
    font-size: 9px;
    font-weight: 600;
    /* Vuetify places it for its own 20px size, which put this one over the icon. */
    bottom: calc(100% - 7px) !important;
    left: calc(100% - 5px) !important;
}

.rail-item.v-btn--active {
    color: rgb(var(--v-theme-secondary)) !important;
}

.rail-flyout {
    min-width: 200px;
    border-radius: 8px !important;
}

.rail-flyout-title {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 40px;
    padding: 0 16px;
    font-size: 15px;
    font-weight: 500;
    color: rgb(var(--v-theme-on-appbar));
    background: rgb(var(--v-theme-appbar));
    border-radius: 8px 8px 0 0;
    text-decoration: none;
}

/* A category of one page is its name alone. */
.rail-flyout-title:only-child {
    border-radius: 8px;
}

.rail-flyout-items {
    padding: 4px !important;

    .v-list-item {
        min-height: 34px !important;
        padding: 0 12px !important;
        border-left: none;
        border-radius: 6px;
        color: rgba(var(--v-theme-on-surface), 0.75);
    }

    .v-list-item:hover {
        color: rgb(var(--v-theme-secondary));
        background: rgba(var(--v-theme-secondary), 0.12);
    }

    .v-list-item--active {
        color: rgb(var(--v-theme-secondary));
        font-weight: 500;
    }

    :deep(.v-list-item__overlay) {
        display: none;
    }

    :deep(.v-list-item__prepend) {
        width: 26px;
    }

    :deep(.v-list-item__prepend > .v-icon) {
        margin-inline-end: 0;
        opacity: 1;
    }

    :deep(.v-list-item-title) {
        font-size: 13px !important;
    }
}
</style>
