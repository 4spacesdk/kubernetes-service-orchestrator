<script setup lang="ts">
import { computed } from "vue";
import { ContainerImageScan } from "@/core/services/Deploy/models";

/**
 * What a scan found, as one chip per severity that has anything - critical and high in
 * colour, the rest quieter. A scan that failed or has not run yet says so instead, and so does
 * one where Trivy could read nothing.
 */
const props = defineProps<{ scan: ContainerImageScan }>();

const chips = computed(() => [
    { label: "C", title: "critical", count: props.scan.critical ?? 0, color: "red-darken-2" },
    { label: "H", title: "high", count: props.scan.high ?? 0, color: "orange-darken-2" },
    { label: "M", title: "medium", count: props.scan.medium ?? 0, color: "amber-darken-3" },
    { label: "L", title: "low", count: props.scan.low ?? 0, color: "grey" },
].filter((chip) => chip.count > 0));
</script>

<template>
    <span class="d-inline-flex align-center ga-1">
        <template v-if="props.scan.status === 'scanned'">
            <v-chip v-for="chip in chips" :key="chip.label" :color="chip.color" size="x-small" label variant="flat">
                {{ chip.label }} {{ chip.count }}
                <v-tooltip activator="parent" location="bottom">{{ chip.count }} {{ chip.title }}</v-tooltip>
            </v-chip>
            <v-chip v-if="!chips.length && (props.scan.targets ?? 0) > 0" color="success" size="x-small" label variant="tonal">none known</v-chip>
            <!-- Nothing Trivy could read - no OS it knows, no lock files - is not a clean image. -->
            <v-chip v-else-if="!chips.length" color="grey" size="x-small" label variant="tonal">
                nothing readable
                <v-tooltip activator="parent" location="bottom">Trivy recognised no operating system and no packages in this image</v-tooltip>
            </v-chip>
        </template>
        <v-chip v-else-if="props.scan.status === 'failed'" color="red" size="x-small" label variant="tonal">
            failed
            <v-tooltip activator="parent" location="bottom">{{ props.scan.error }}</v-tooltip>
        </v-chip>
        <v-chip v-else size="x-small" label variant="tonal">{{ props.scan.status }}</v-chip>
    </span>
</template>
