<script setup lang="ts">
/**
 * The name in a list row, as the row's default action (LIST-4, #53). A link rather than a
 * span, so it is reachable with Tab and opened with Enter.
 */
const emit = defineEmits<{
    (e: "click", event: MouseEvent): void;
}>();
</script>

<template>
    <a href="#" class="name-link" @click.prevent="emit('click', $event)"><slot /></a>
</template>

<style scoped>
.name-link {
    color: inherit;
    text-decoration: none;
    font-weight: 500;
}

.name-link:hover,
.name-link:focus-visible {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
}
</style>
