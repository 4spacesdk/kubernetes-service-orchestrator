<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Line } from "vue-chartjs";
import {
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
    type ChartData,
    type ChartOptions,
    type TooltipItem,
} from "chart.js";
import moment from "moment";
import { Api } from "@/core/services/Deploy/Api";
import type { ContainerImageScanRecord } from "@/core/services/Deploy/models";

Chart.register(CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend);

/**
 * Critical and high over time, for one tag. A point where the tag pointed at a new image is
 * drawn larger: a tag like `develop` changes, and a drop may be a new image rather than a fix
 * - and a rise with the same image is newly published vulnerabilities.
 */
const props = defineProps<{
    containerImageId: number;
    tag: string;
}>();

const records = ref<ContainerImageScanRecord[]>([]);
const isLoading = ref(false);

onMounted(load);
watch(() => [props.containerImageId, props.tag], load);

function load() {
    isLoading.value = true;
    Api.containerImageScanRecords().get()
        .where("container_image_id", props.containerImageId)
        .where("tag", props.tag)
        .orderAsc("scanned_at")
        .find((items) => {
            records.value = items;
            isLoading.value = false;
        });
}

function isNewImage(index: number): boolean {
    return index > 0 && records.value[index].digest !== records.value[index - 1].digest;
}

const data = computed<ChartData<"line">>(() => {
    const radius = records.value.map((_, i) => (isNewImage(i) ? 6 : 2));
    const series = (label: string, color: string, value: (r: ContainerImageScanRecord) => number) => ({
        label,
        data: records.value.map(value),
        borderColor: color,
        backgroundColor: color,
        pointRadius: radius,
        pointHoverRadius: radius.map((r) => r + 2),
        tension: 0.2,
    });

    return {
        labels: records.value.map((r) => moment(r.scanned_at).format("DD/MM-YY")),
        datasets: [
            // Vuetify's red-darken-2 and orange-darken-2, as on the chips.
            series("Critical", "#D32F2F", (r) => r.critical ?? 0),
            series("High", "#F57C00", (r) => r.high ?? 0),
        ],
    };
});

const options = computed<ChartOptions<"line">>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: "index", intersect: false },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 12 } },
        tooltip: {
            callbacks: {
                title: (items: TooltipItem<"line">[]) => moment(records.value[items[0].dataIndex].scanned_at).format("DD/MM-YY HH:mm"),
                footer: (items: TooltipItem<"line">[]) => (isNewImage(items[0].dataIndex) ? "New image" : ""),
            },
        },
    },
}));
</script>

<template>
    <div>
        <v-progress-linear v-if="isLoading" indeterminate />
        <div v-else-if="records.length < 2" class="text-caption text-medium-emphasis">
            A graph over time once the tag has been scanned more than once.
        </div>
        <div v-else class="scan-history-chart">
            <Line :data="data" :options="options" />
        </div>
    </div>
</template>

<style scoped>
.scan-history-chart {
    height: 180px;
}
</style>
