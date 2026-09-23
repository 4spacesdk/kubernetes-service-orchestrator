<script setup lang="ts">
import { computed, ref } from "vue";
import { useMenuCategories } from "@/components/Shell/Menu/useMenuCategories";

/**
 * The menu on a phone: what is looked at and acted on from one - workspaces, deployments and
 * updates - along the bottom, where a thumb reaches, and the rest behind More. Only what the
 * user may see: a place that is not in their menu is not here either.
 */
const { categories, isCategoryActive, isItemActive, badgeOf, categoryUrl } = useMenuCategories();

const showMore = ref(false);

interface Shortcut {
    title: string;
    icon: string;
    url: string;
    isActive: boolean;
    badge?: { count: number; color: string } | null;
}

const shortcuts = computed<Shortcut[]>(() => {
    const result: Shortcut[] = [];
    const workspaces = categories.value.find((category) => category.identifier == "sites");
    if (workspaces) {
        result.push({
            title: workspaces.name,
            icon: workspaces.icon,
            url: categoryUrl(workspaces)!,
            isActive: isCategoryActive(workspaces),
            badge: badgeOf(workspaces),
        });
    }
    const deployments = categories.value
        .find((category) => category.identifier == "setup")
        ?.items.find((item) => item.url == "/setup/deployments");
    if (deployments) {
        result.push({
            title: deployments.title,
            icon: deployments.icon ?? "fa fa-cubes",
            url: deployments.url,
            isActive: isItemActive(deployments.url),
            badge: deployments.badge ? { count: deployments.badge, color: deployments.badgeColor ?? "secondary" } : null,
        });
    }
    const updates = categories.value.find((category) => category.identifier == "auto-updates");
    if (updates) {
        result.push({
            title: updates.name,
            icon: updates.icon,
            url: categoryUrl(updates)!,
            isActive: isCategoryActive(updates),
            badge: badgeOf(updates),
        });
    }
    return result;
});

/** More is lit when the page is one of those behind it. */
const isMoreActive = computed(() => !shortcuts.value.some((shortcut) => shortcut.isActive));
</script>

<template>
    <v-bottom-navigation
        grow
        color="secondary"
        height="56"
        class="bottom-menu"
    >
        <v-btn
            v-for="shortcut in shortcuts"
            :key="shortcut.url"
            :to="shortcut.url"
            :active="shortcut.isActive"
        >
            <v-badge
                v-if="shortcut.badge"
                :color="shortcut.badge.color"
                :content="shortcut.badge.count"
            >
                <v-icon size="18">{{ shortcut.icon }}</v-icon>
            </v-badge>
            <v-icon v-else size="18">{{ shortcut.icon }}</v-icon>
            <span>{{ shortcut.title }}</span>
        </v-btn>

        <v-btn
            :active="isMoreActive && !showMore"
            @click="showMore = true"
        >
            <v-icon size="18">fa fa-ellipsis</v-icon>
            <span>More</span>
        </v-btn>
    </v-bottom-navigation>

    <v-bottom-sheet v-model="showMore">
        <v-card class="more-sheet">
            <v-list density="compact" nav>
                <template v-for="category in categories" :key="category.identifier">
                    <v-list-subheader v-if="category.items.length > 1">{{ category.name }}</v-list-subheader>
                    <template v-if="category.items.length > 1">
                        <v-list-item
                            v-for="item in category.items"
                            :key="item.url"
                            :to="item.url"
                            :prepend-icon="item.icon ?? category.icon"
                            :title="item.title"
                            color="secondary"
                            @click="showMore = false"
                        />
                    </template>
                    <v-list-item
                        v-else
                        :to="category.items[0].url"
                        :prepend-icon="category.icon"
                        :title="category.name"
                        color="secondary"
                        @click="showMore = false"
                    />
                </template>
            </v-list>
        </v-card>
    </v-bottom-sheet>
</template>

<style scoped>
.bottom-menu :deep(.v-btn__content) {
    flex-direction: column;
    gap: 2px;
    font-size: 11px;
    text-transform: none;
}

.more-sheet {
    max-height: 75vh;
    overflow-y: auto;
    padding-bottom: env(safe-area-inset-bottom);
}

.more-sheet .v-list-item {
    min-height: 40px !important;
}

.more-sheet :deep(.v-list-item__prepend) {
    width: 36px;
}

.more-sheet :deep(.v-list-item__prepend > .v-icon) {
    margin-inline-end: 0;
}

.more-sheet .v-list-item:not(:last-of-type) {
    border-bottom: none;
}
</style>
