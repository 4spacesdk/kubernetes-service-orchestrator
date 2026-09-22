<script setup lang="ts">
/**
 * One card on a dashboard page: a title, the card's own buttons beside it, and what it shows.
 */
const props = defineProps<{
    title: string;
    icon?: string;
}>();
</script>

<template>
    <section class="dashboard-card">
        <header class="dashboard-card-header">
            <v-icon
                v-if="props.icon"
                size="14"
                class="dashboard-card-icon">{{ props.icon }}</v-icon>
            <h2 class="dashboard-card-title">{{ props.title }}</h2>
            <div class="d-flex align-center ga-1 ml-auto">
                <slot name="actions"/>
            </div>
        </header>
        <div class="dashboard-card-body">
            <slot/>
        </div>
    </section>
</template>

<style scoped>
.dashboard-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    background: #fff;
}

.dashboard-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 12px 0 16px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.dashboard-card-icon {
    color: #2e92a3;
}

.dashboard-card-title {
    font-size: 14px;
    font-weight: 500;
}

.dashboard-card-body {
    padding: 12px 16px;
    flex-grow: 1;
}
</style>
