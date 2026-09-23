<script setup lang="ts">
import {computed} from 'vue'
import {useRoute} from "vue-router";
import AuthService from "@/services/AuthService";
import {visibleMenuCategories} from "@/components/Shell/Menu/menuCategories";
import OverviewCard from "@/components/Pages/Overview/OverviewCard.vue";
import {itemBadge, useMenuBadges} from "@/components/Shell/Menu/menuBadges";

/**
 * A menu category's pages, as cards, for the category's own menu item. The menu's popover is
 * the quick way to one of them; this is where to look around first.
 */
const route = useRoute();

// The same numbers as the menu, on the cards they belong to.
useMenuBadges();

const category = computed(() => visibleMenuCategories(AuthService.currentAuthUser?.allPermissions ?? [])
    .find(category => category.identifier == route.meta.category));

</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar
            density="compact"
            flat
            color="toolbar"
            dark
        >
            <v-toolbar-title>{{ category?.name }}</v-toolbar-title>
        </v-toolbar>

        <div
            v-if="category"
            class="overview">
            <OverviewCard
                v-for="item in category.items"
                :key="item.url"
                :to="item.url"
                :icon="item.icon ?? category.icon"
                :title="item.title"
                :description="item.description"
                :badge="itemBadge(item.url)"/>
        </div>
        <div
            v-else
            class="pa-4">
            There is nothing here you have access to.
        </div>
    </div>
</template>

<style scoped>
.overview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 12px;
    padding: 16px;
}
</style>
