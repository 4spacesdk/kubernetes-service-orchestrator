<script setup lang="ts">
import {computed} from 'vue'
import {useRoute} from "vue-router";
import AuthService from "@/services/AuthService";
import {visibleMenuCategories} from "@/components/Shell/Menu/menuCategories";

/**
 * A menu category's pages, as cards, for the category's own menu item. The menu's popover is
 * the quick way to one of them; this is where to look around first.
 */
const route = useRoute();

const category = computed(() => visibleMenuCategories(AuthService.currentAuthUser?.allPermissions ?? [])
    .find(category => category.identifier == route.meta.category));

</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>{{ category?.name }}</v-toolbar-title>
        </v-toolbar>

        <div
            v-if="category"
            class="overview">
            <router-link
                v-for="item in category.items"
                :key="item.url"
                :to="item.url"
                class="overview-card">
                <span class="overview-icon">
                    <v-icon size="16">{{ item.icon ?? category.icon }}</v-icon>
                </span>
                <div>
                    <div class="overview-title">{{ item.title }}</div>
                    <div
                        v-if="item.description"
                        class="overview-description">{{ item.description }}</div>
                </div>
            </router-link>
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

.overview-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    color: inherit;
    text-decoration: none;
    transition: border-color .15s, background-color .15s;
}

.overview-card:hover,
.overview-card:focus-visible {
    border-color: #2e92a3;
    background: rgba(46, 146, 163, 0.06);
}

.overview-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 8px;
    color: #2e92a3;
    background: rgba(46, 146, 163, 0.12);
}

.overview-title {
    font-size: 14px;
    font-weight: 500;
}

.overview-description {
    margin-top: 2px;
    font-size: 12px;
    color: rgba(0, 0, 0, 0.6);
}
</style>
