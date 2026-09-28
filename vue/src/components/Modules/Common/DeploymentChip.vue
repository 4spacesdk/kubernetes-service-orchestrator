<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import type { Deployment } from "@/core/services/Deploy/models";

/**
 * A deployment named in another thing's row - a migration job, an update - as
 * `name.namespace`, linking to the deployment's page.
 *
 * A link rather than a click handler, so Cmd/Ctrl-click opens it in a new tab and Tab reaches
 * it. Not a link on the deployment's own page, where it would lead to where you already are.
 */
const props = defineProps<{
    deployment: Deployment;
}>();

const route = useRoute();

const label = computed(() => `${props.deployment.name}.${props.deployment.namespace}`);

const isHere = computed(() => route.name == "DeploymentById" && route.params.id == String(props.deployment.id));

const to = computed(() => isHere.value || !props.deployment.id
    ? undefined
    : { name: "DeploymentById", params: { id: props.deployment.id } });
</script>

<template>
    <v-chip :to="to" class="deployment-chip">
        <span class="text-truncate">{{ label }}</span>
        <v-tooltip activator="parent" location="bottom">{{ label }}</v-tooltip>
    </v-chip>
</template>

<style scoped>
.deployment-chip {
    max-width: 200px;
}
</style>
