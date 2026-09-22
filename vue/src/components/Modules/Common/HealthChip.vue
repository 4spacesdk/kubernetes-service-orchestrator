<script setup lang="ts">
import { computed } from "vue";
import moment from "moment";
import { HealthStatusTypes } from "@/constants";

/**
 * A deployment's or a workspace's runtime health.
 *
 * Beside the status, never instead of it: the status says whether kso's resources are in the
 * cluster, this says whether the workload is doing well. The reason is in the tooltip, with
 * how long the health has been what it is.
 *
 * A check that has stopped (`checkedAt` more than a few minutes old) leaves the last health
 * shown, faded, rather than turning every row into Unknown - the status bar is where a
 * cluster that does not answer is reported, once.
 */
const props = defineProps<{
    health?: string | null;
    reason?: string | null;
    changedAt?: string | null;
    checkedAt?: string | null;
    /** While whatever the chip opens is open, so the tooltip does not sit on top of it. */
    hideTooltip?: boolean;
}>();

/** Minutes after which a health is old news. The check runs every minute. */
const StaleAfterMinutes = 3;

const looks: Record<string, { title: string; icon: string; color: string }> = {
    [HealthStatusTypes.Healthy]: { title: "Healthy", icon: "fa fa-heart", color: "success" },
    [HealthStatusTypes.Progressing]: { title: "Progressing", icon: "fa fa-arrows-rotate", color: "info" },
    [HealthStatusTypes.Degraded]: { title: "Degraded", icon: "fa fa-heart-crack", color: "error" },
    [HealthStatusTypes.Missing]: { title: "Missing", icon: "fa fa-ghost", color: "warning" },
    [HealthStatusTypes.Suspended]: { title: "Suspended", icon: "fa fa-pause", color: "grey" },
    [HealthStatusTypes.Unknown]: { title: "Unknown", icon: "fa fa-question", color: "grey" },
};

const look = computed(() => (props.health ? looks[props.health] ?? looks[HealthStatusTypes.Unknown] : null));

const isStale = computed(() => {
    if (!props.checkedAt) {
        return false;
    }
    return moment().diff(moment(props.checkedAt), "minutes") >= StaleAfterMinutes;
});

const since = computed(() => (props.changedAt ? moment(props.changedAt).fromNow() : null));
</script>

<template>
    <span v-if="!look" class="text-medium-emphasis">—</span>
    <v-chip
        v-else
        :color="look.color"
        :class="{ stale: isStale }"
        size="small"
        label
        variant="tonal"
    >
        <v-icon start size="x-small" :class="{ 'fa-spin': health === HealthStatusTypes.Progressing && !isStale }">{{ look.icon }}</v-icon>
        {{ look.title }}
        <v-icon v-if="isStale" end size="x-small">fa fa-clock</v-icon>

        <v-tooltip activator="parent" location="bottom" max-width="420" :disabled="hideTooltip">
            <div class="font-weight-bold">{{ look.title }}<span v-if="since"> since {{ since }}</span></div>
            <div v-if="reason" class="reason">{{ reason }}</div>
            <div v-if="isStale" class="mt-1">
                Last checked {{ moment(checkedAt!).fromNow() }} - the check may have stopped.
            </div>
        </v-tooltip>
    </v-chip>
</template>

<style scoped>
.stale {
    opacity: 0.55;
}

.reason {
    white-space: pre-line;
    word-break: break-word;
}
</style>
