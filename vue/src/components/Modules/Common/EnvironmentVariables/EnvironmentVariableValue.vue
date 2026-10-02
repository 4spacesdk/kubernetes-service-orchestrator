<script setup lang="ts">
import {computed} from "vue";
import {describeGeneratedSecret, parseGeneratedSecret} from "@/components/Modules/Common/EnvironmentVariables/environmentVariables";

/**
 * A variable's value in a list: the value, or for a secret one that it is secret and whether
 * one is stored - the server never sends it - or the secret kso makes for it.
 */
const props = defineProps<{
    variable: { value?: string; is_secret?: boolean; has_value?: boolean };
}>();

const generated = computed(() => props.variable.is_secret ? null : parseGeneratedSecret(props.variable.value));
</script>

<template>
    <span v-if="props.variable.is_secret" class="d-inline-flex align-center ga-1 text-medium-emphasis mt-1">
        <v-icon size="x-small">fa fa-lock</v-icon>
        <span v-if="props.variable.value">new value</span>
        <span v-else-if="props.variable.has_value">secret</span>
        <span v-else>secret, no value</span>
    </span>
    <span v-else-if="generated" class="d-inline-flex align-center ga-1 mt-1">
        <v-icon size="x-small" class="text-medium-emphasis">fa fa-wand-magic-sparkles</v-icon>
        <span>{{ generated.name }}</span>
        <span class="text-medium-emphasis">- {{ describeGeneratedSecret(generated) }}{{ generated.owner === 'workspace' ? ', the workspace\'s' : '' }}</span>
    </span>
    <span
        v-else
        class="text-truncate d-inline-block mt-1"
        style="max-width: 300px;">{{ props.variable.value }}</span>
</template>
