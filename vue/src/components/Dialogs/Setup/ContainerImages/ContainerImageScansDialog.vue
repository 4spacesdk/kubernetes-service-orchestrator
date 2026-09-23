<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Api } from "@/core/services/Deploy/Api";
import { ContainerImage, ContainerImageScan } from "@/core/services/Deploy/models";
import DateView from "@/components/Modules/Common/DateView.vue";
import ScanHistoryChart from "@/components/Modules/Setup/ContainerImages/ScanHistoryChart/ScanHistoryChart.vue";
import ScanCounts from "@/components/Modules/Setup/ContainerImages/ScanCounts/ScanCounts.vue";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import bus from "@/plugins/bus";
import moment from "moment";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import type { PushSubscription } from "@/services/Push/PushSubscription";

/**
 * What Trivy found in the tags of an image that deployments run - scanned every night, or
 * within the minute after "Scan now" - and in any other tag scanned by hand, such as one
 * nothing deploys yet.
 */
export interface ContainerImageScansDialog_Input {
    containerImage: ContainerImage;
}

interface Finding {
    id: string;
    link: string;
    package: string;
    installed: string;
    fixed: string;
    severity: string;
    title: string;
}

const props = defineProps<{ input: ContainerImageScansDialog_Input; events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const scans = ref<ContainerImageScan[]>([]);
const selected = ref<number | null>(null);
const findings = ref<Finding[]>([]);
const isLoading = ref(false);
const isLoadingFindings = ref(false);
const isQueuing = ref(false);
const search = ref("");
const subscription = ref<PushSubscription>();
/** Bumped when a scan finishes, so the graph reads its records again. */
const historyVersion = ref(0);

/**
 * A tag to scan by hand. The registry's tags are offered, newest first with when each was
 * pushed; any can be typed.
 */
const manualTag = ref<string | null>(null);
const registryTags = ref<{ name: string; pushed?: string }[]>([]);
const isLoadingRegistryTags = ref(false);
const isQueuingTag = ref(false);

const headers = [
    { title: "Severity", key: "severity", sortable: false },
    { title: "Id", key: "id" },
    { title: "Package", key: "package" },
    { title: "Installed", key: "installed", sortable: false },
    { title: "Fix", key: "fixed", sortable: false },
    { title: "Title", key: "title", sortable: false },
];

const severityColors: Record<string, string> = {
    critical: "red-darken-2",
    high: "orange-darken-2",
    medium: "amber-darken-3",
    low: "grey",
    unknown: "grey-lighten-1",
};

const current = computed(() => scans.value.find((scan) => scan.id === selected.value));

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;

    subscription.value = PushService.subscribe(
        Events.ContainerImage_Scans_Changed(props.input.containerImage.id!),
        (data) => onScanChanged(new ContainerImageScan(data.next)),
    );
    loadScans();
    loadRegistryTags();
});

onUnmounted(() => {
    subscription.value?.unsubscribe();
});

/**
 * A scan was queued, started or finished. The status and counts come with the event; the
 * findings and the graph are read again once it is done.
 */
function onScanChanged(changed: ContainerImageScan) {
    const index = scans.value.findIndex((scan) => scan.id === changed.id);
    if (index === -1) {
        loadScans();
        return;
    }
    scans.value[index] = changed;
    const isDone = changed.status === "scanned" || changed.status === "failed";
    if (isDone && changed.id === selected.value) {
        loadFindings();
        historyVersion.value++;
    }
}

/**
 * The list carries the counts; the findings of the chosen tag come from reading that one scan.
 */
function loadScans() {
    isLoading.value = true;
    Api.containerImageScans().get()
        .where("container_image_id", props.input.containerImage.id!)
        .find((items) => {
            scans.value = items.sort((a, b) => (b.scanned_at ?? "").localeCompare(a.scanned_at ?? ""));
            isLoading.value = false;
            if (!scans.value.some((scan) => scan.id === selected.value)) {
                selected.value = scans.value[0]?.id ?? null;
            }
            loadFindings();
        });
}

function loadFindings() {
    findings.value = [];
    if (!selected.value) {
        return;
    }
    isLoadingFindings.value = true;
    Api.containerImageScans().getById(selected.value).find((items) => {
        try {
            findings.value = JSON.parse(items[0]?.findings ?? "[]");
        } catch {
            findings.value = [];
        }
        isLoadingFindings.value = false;
    });
}

/** A registry that refuses leaves the field to typing; the scan itself says what went wrong. */
function loadRegistryTags() {
    isLoadingRegistryTags.value = true;
    const api = Api.containerImages().getTagsGetById(props.input.containerImage.id!);
    api.setErrorHandler(() => {
        isLoadingRegistryTags.value = false;
        return false;
    });
    api.find((responses) => {
        registryTags.value = [...(responses[0]?.tags ?? [])]
            .filter((tag) => tag.name)
            .sort((a, b) => (b.pushed_at ?? "").localeCompare(a.pushed_at ?? ""))
            .map((tag) => ({
                name: tag.name!,
                pushed: tag.pushed_at ? moment(tag.pushed_at).format("D/M-YY HH:mm") : undefined,
            }));
        isLoadingRegistryTags.value = false;
    });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onTagChanged() {
    loadFindings();
}

function onScanNowBtnClicked() {
    isQueuing.value = true;
    Api.containerImages().scanPutById(props.input.containerImage.id!).save(null, (response) => {
        isQueuing.value = false;
        const queued = response.queued ?? 0;
        bus.emit("toast", {
            text: queued
                ? `${queued} tag${queued === 1 ? "" : "s"} queued - the scan runs within a minute`
                : "No deployment runs this image, so there is nothing to scan",
        });
        loadScans();
    });
}

function onScanTagBtnClicked() {
    const tag = manualTag.value?.trim();
    if (!tag || isQueuingTag.value) {
        return;
    }
    isQueuingTag.value = true;
    const api = Api.containerImages().scanPutById(props.input.containerImage.id!).tag(tag);
    api.setErrorHandler((response) => {
        isQueuingTag.value = false;
        bus.emit("toast", { text: response.error ?? "Could not queue the scan", color: "error" });
        return false;
    });
    api.save(null, () => {
        isQueuingTag.value = false;
        manualTag.value = null;
        bus.emit("toast", { text: `${tag} queued - the scan runs within a minute` });
        Api.containerImageScans().get()
            .where("container_image_id", props.input.containerImage.id!)
            .find((items) => {
                scans.value = items.sort((a, b) => (b.scanned_at ?? "").localeCompare(a.scanned_at ?? ""));
                selected.value = scans.value.find((scan) => scan.tag === tag)?.id ?? selected.value;
                loadFindings();
            });
    });
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>
</script>

<template>
    <v-dialog persistent height="85vh" width="70vw" min-width="640" v-model="showDialog">
        <v-card class="w-100 h-100">
            <v-card-title>Vulnerabilities · {{ props.input.containerImage.name }}</v-card-title>
            <v-divider />
            <!-- px-0 whether or not there is a table (main.scss only drops the padding when there
                 is): the table goes edge to edge, and what is above it keeps a margin. -->
            <v-card-text class="px-0">
                <div class="px-4">
                    <div class="text-caption text-medium-emphasis mb-3">
                        The tags deployments run, scanned by Trivy every night. Any other tag can be
                        scanned by hand - it is kept for 30 days.
                    </div>
                    <div class="d-flex align-center ga-2 mb-4">
                        <v-combobox
                            v-model="manualTag"
                            :items="registryTags"
                            item-title="name"
                            item-value="name"
                            :return-object="false"
                            :loading="isLoadingRegistryTags"
                            label="Scan a tag"
                            placeholder="Choose or type a tag"
                            density="compact"
                            variant="outlined"
                            hide-details
                            clearable
                            style="max-width: 320px"
                            @keydown.enter.stop="onScanTagBtnClicked"
                        >
                            <template v-slot:item="{ props: itemProps, item }">
                                <v-list-item v-bind="itemProps">
                                    <template v-slot:append>
                                        <span
                                            v-if="item.raw.pushed"
                                            class="text-caption text-medium-emphasis ml-4">{{ item.raw.pushed }}</span>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-combobox>
                        <v-btn
                            variant="tonal"
                            color="primary"
                            prepend-icon="fa fa-shield-halved"
                            :disabled="!manualTag?.trim()"
                            :loading="isQueuingTag"
                            @click="onScanTagBtnClicked"
                        >
                            Scan
                        </v-btn>
                    </div>
                    <v-progress-linear v-if="isLoading" indeterminate class="mb-2" />
                    <div v-else-if="!scans.length" class="text-body-2 text-medium-emphasis">
                        Not scanned yet. Scans cover the tags deployments run; use "Scan now", wait for the nightly scan, or scan a tag above.
                    </div>

                    <template v-if="current">
                        <div class="d-flex align-center flex-wrap ga-4 mb-3">
                            <v-select
                                v-model="selected"
                                :items="scans"
                                item-title="tag"
                                item-value="id"
                                label="Tag"
                                density="compact"
                                variant="outlined"
                                hide-details
                                style="max-width: 260px"
                                @update:model-value="onTagChanged"
                            />
                            <ScanCounts :scan="current" />
                            <span v-if="current.scanned_at" class="text-body-2 text-medium-emphasis">
                                scanned <DateView :date-string="current.scanned_at" text-format="DD/MM-YY HH:mm" />
                            </span>
                        </div>
                        <div class="text-caption text-medium-emphasis mb-2">
                            <code>{{ current.image_reference }}</code>
                            <span v-if="current.operating_system"> · {{ current.operating_system }}</span>
                        </div>
                        <ScanHistoryChart :key="historyVersion" :container-image-id="props.input.containerImage.id!" :tag="current.tag!" class="mb-4" />
                        <v-alert v-if="current.status === 'failed'" density="compact" variant="tonal" type="error" class="mb-3">
                            {{ current.error }}
                        </v-alert>

                        <v-text-field
                            v-if="findings.length"
                            v-model="search"
                            placeholder="Search findings"
                            prepend-inner-icon="fa fa-magnifying-glass"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                            class="mb-2"
                        />
                    </template>
                </div>

                <template v-if="current">
                    <v-data-table
                        v-if="current.status === 'scanned' || findings.length"
                        :headers="headers"
                        :items="findings"
                        :search="search"
                        :loading="isLoadingFindings"
                        :items-per-page="25"
                        density="compact"
                        no-data-text="Nothing known."
                    >
                        <template v-slot:item.severity="{ item }">
                            <v-chip :color="severityColors[item.severity] ?? 'grey'" size="x-small" label variant="flat">
                                {{ item.severity }}
                            </v-chip>
                        </template>
                        <template v-slot:item.id="{ item }">
                            <a class="text-no-wrap" v-if="item.link" :href="item.link" target="_blank" rel="noopener noreferrer">{{ item.id }}</a>
                            <span v-else class="text-no-wrap">{{ item.id }}</span>
                        </template>
                    </v-data-table>
                </template>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>
                <v-btn variant="tonal" prepend-icon="fa fa-rotate" :loading="isLoading" @click="loadScans"> Refresh </v-btn>
                <v-btn variant="tonal" color="primary" prepend-icon="fa fa-shield-halved" :loading="isQueuing" @click="onScanNowBtnClicked"> Scan now </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
