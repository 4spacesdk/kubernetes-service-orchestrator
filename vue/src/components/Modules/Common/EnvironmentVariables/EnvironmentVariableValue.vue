<script setup lang="ts">
/**
 * A variable's value in a list: the value, or for a secret one that it is secret and whether
 * one is stored - the server never sends it.
 */
const props = defineProps<{
    variable: { value?: string; is_secret?: boolean; has_value?: boolean };
}>();
</script>

<template>
    <span v-if="props.variable.is_secret" class="d-inline-flex align-center ga-1 text-medium-emphasis mt-1">
        <v-icon size="x-small">fa fa-lock</v-icon>
        <span v-if="props.variable.value">new value</span>
        <span v-else-if="props.variable.has_value">secret</span>
        <span v-else>secret, no value</span>
    </span>
    <span
        v-else
        class="text-truncate d-inline-block mt-1"
        style="max-width: 300px;">{{ props.variable.value }}</span>
</template>
