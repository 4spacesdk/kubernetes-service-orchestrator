<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Workspace } from "@/core/services/Deploy/models";
import { PushSubscription } from "@/services/Push/PushSubscription";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import HealthChip from "@/components/Modules/Common/HealthChip.vue";

/**
 * The worst health among the workspace's deployments, and which of them it comes from, kept
 * current by push.
 */
const props = defineProps<{
    workspace: Workspace;
}>();

const emit = defineEmits<{
    (e: "click"): void;
}>();

const health = ref<string | undefined>();
const reason = ref<string | undefined>();
const changedAt = ref<string | undefined>();
const pushSubscription = ref<PushSubscription>();

function render(workspace: Workspace) {
    health.value = workspace.health;
    reason.value = workspace.health_reason;
    changedAt.value = workspace.health_changed_at;
}

function subscribe() {
    pushSubscription.value?.unsubscribe();
    pushSubscription.value = PushService.subscribe(Events.Workspace_Changed_Health(props.workspace.id!), (data) => {
        render(new Workspace(data.next));
    });
}

onMounted(() => {
    render(props.workspace);
    subscribe();
});

watch(
    () => props.workspace,
    (workspace) => {
        render(workspace);
        subscribe();
    }
);

onUnmounted(() => {
    pushSubscription.value?.unsubscribe();
});
</script>

<template>
    <span :class="{ clickable: !!health }" @click="health && emit('click')">
        <HealthChip :health="health" :reason="reason" :changed-at="changedAt" />
    </span>
</template>

<style scoped>
.clickable {
    cursor: pointer;
}
</style>
