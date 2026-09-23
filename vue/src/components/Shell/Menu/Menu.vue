<script setup lang="ts">
import { computed } from "vue";
import { useRouter } from "vue-router";
import { useDisplay } from "vuetify";
import { type MenuCategory } from "@/components/Shell/Menu/menuCategories";
import { useMenuCategories } from "@/components/Shell/Menu/useMenuCategories";

const router = useRouter();

const { categories, isCategoryActive, badgeOf, categoryUrl } = useMenuCategories();

/**
 * The menu stands open where there is room for it, and folds in to a rail of icons where there
 * is not. The lists need about 1030px, so `lg` and up (1280px) keeps both; below that the menu
 * is what gives way. Folded, it stays folded: hovering an icon shows its name, and a
 * category's pages, beside it, over the page, so nothing moves. On a phone it is the bottom
 * menu instead - see BottomMenu.
 */
const { lgAndUp } = useDisplay();
const isRail = computed(() => !lgAndUp.value);

function onLogoClicked(event: Event) {
    router
        .push({
            name: "Dashboard",
        })
        .catch((_) => {});
}

/**
 * The popover beside a rail icon (48px). A category of pages opens with its name above the
 * icon and its first page level with it; one of a single page shows its name level with it.
 */
function flyoutOffset(category: MenuCategory): number[] {
    return category.items.length > 1 ? [8, 37] : [8, -4];
}

</script>

<template>
    <v-navigation-drawer
        color="surface"
        permanent
        :rail="isRail"
        :rail-width="56"
        flat
        elevation="0"
    >
        <nav v-if="isRail" class="rail">
            <v-menu
                v-for="(category, index) in categories"
                :key="index"
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
                    <v-list v-if="category.items.length > 1" density="compact" class="rail-flyout-items">
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

        <v-list v-else dense nav class="py-1">
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
                    v-if="category.items.length === 1"
                    :to="category.items[0].url"
                    link
                >
                    <template v-slot:prepend>
                        <v-icon size="16">{{ category.icon }}</v-icon>
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
                    :value="category.active"
                >
                    <template v-slot:activator="{ props }">
                        <v-list-item v-bind="props">
                            <template v-slot:prepend>
                                <v-icon size="16">{{ category.icon }}</v-icon>
                            </template>
                            <v-list-item-title>{{
                                category.name
                            }}</v-list-item-title>
                        </v-list-item>
                    </template>

                    <div v-if="category.url" class="category-item">
                        <v-list-item :to="category.url" link exact>
                            <v-list-item-title>Overview</v-list-item-title>
                        </v-list-item>
                    </div>
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
    color: rgba(0, 0, 0, 0.6) !important;
}

.rail-item.v-btn--active {
    color: #2e92a3 !important;
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
    color: #fff;
    background: #1a3b46;
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
        color: rgba(0, 0, 0, 0.75);
    }

    .v-list-item:hover {
        color: #2e92a3;
        background: rgba(46, 146, 163, 0.12);
    }

    .v-list-item--active {
        color: #2e92a3;
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
