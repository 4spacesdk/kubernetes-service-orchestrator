<script setup lang="ts">
/** A page on an overview: its icon, its name and what it is for. */
defineProps<{
    to: string;
    icon: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <router-link
        :to="to"
        class="overview-card">
        <span class="overview-icon">
            <v-icon size="16">{{ icon }}</v-icon>
        </span>
        <div class="overview-text">
            <div class="overview-title">{{ title }}</div>
            <div
                v-if="description"
                class="overview-description">{{ description }}</div>
        </div>
    </router-link>
</template>

<style scoped>
.overview-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
    color: inherit;
    text-decoration: none;
    transition: border-color .15s, background-color .15s;
}

.overview-card:hover,
.overview-card:focus-visible {
    border-color: rgb(var(--v-theme-secondary));
    background: rgba(var(--v-theme-secondary), 0.06);
}

.overview-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 8px;
    color: rgb(var(--v-theme-secondary));
    background: rgba(var(--v-theme-secondary), 0.12);
}

.overview-text {
    min-width: 0;
}

.overview-title {
    font-size: 14px;
    font-weight: 500;
}

.overview-description {
    margin-top: 2px;
    font-size: 12px;
    color: rgba(var(--v-theme-on-background), 0.6);
}
</style>
