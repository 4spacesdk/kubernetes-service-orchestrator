<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Deployment } from "@/core/services/Deploy/models";
import { PushSubscription } from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import HealthChip from "@/components/Modules/Common/HealthChip.vue";
import DeploymentPodsButton from "@/components/Modules/Setup/Deployments/DeploymentPodsButton/DeploymentPodsButton.vue";

/**
 * The deployment's health on its row, kept current by push. A click opens its pods - where a
 * crash loop or an image that cannot be pulled is looked into.
 */
const props = defineProps<{
    deployment: Deployment;
}>();

const health = ref<string | undefined>();
const reason = ref<string | undefined>();
const changedAt = ref<string | undefined>();
const checkedAt = ref<string | undefined>();
const pushSubscription = ref<PushSubscription>();
const showPods = ref(false);

function render(deployment: Deployment) {
    health.value = deployment.health;
    reason.value = deployment.health_reason;
    changedAt.value = deployment.health_changed_at;
    checkedAt.value = deployment.health_checked_at;
}

function subscribe() {
    pushSubscription.value?.unsubscribe();
    pushSubscription.value = PushService.subscribe(Events.Deployment_Changed_Health(props.deployment.id!), (data) => {
        render(new Deployment(data.next));
    });
}

onMounted(() => {
    render(props.deployment);
    subscribe();
});

watch(
    () => props.deployment,
    (deployment) => {
        render(deployment);
        subscribe();
    }
);

onUnmounted(() => {
    pushSubscription.value?.unsubscribe();
});
</script>

<template>
    <v-menu v-if="health" v-model="showPods" min-width="550">
        <template v-slot:activator="{ props: activator }">
            <span v-bind="activator" class="clickable">
                <HealthChip :health="health" :reason="reason" :changed-at="changedAt" :checked-at="checkedAt" :hide-tooltip="showPods" />
            </span>
        </template>
        <DeploymentPodsButton :deployment="props.deployment" />
    </v-menu>
    <HealthChip v-else />
</template>

<style scoped>
.clickable {
    cursor: pointer;
}
</style>
