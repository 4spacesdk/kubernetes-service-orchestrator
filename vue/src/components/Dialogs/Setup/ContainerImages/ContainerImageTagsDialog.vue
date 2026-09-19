<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { ContainerImage } from "@/core/services/Deploy/models";
import type { ContainerImageTag } from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import ApiService from "@/services/ApiService";

/**
 * The tags an image's registry reports (FEAT-1) - mostly a quick check that the registry's
 * credentials work for this image, so a refusal is shown with its reason.
 */
export interface ContainerImageTagsDialog_Input {
    containerImage: ContainerImage;
}

const props = defineProps<{ input: ContainerImageTagsDialog_Input; events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const tags = ref<ContainerImageTag[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);
const search = ref("");

/**
 * Last pushed first. A tag the registry gives no time for goes last, highest version first -
 * the server answers oldest version first.
 */
const rows = computed(() => {
    const term = search.value?.toLowerCase() ?? "";
    return [...tags.value]
        .reverse()
        .sort((a, b) => (b.pushed_at ?? "").localeCompare(a.pushed_at ?? ""))
        .filter((tag) => !term || tag.name!.toLowerCase().includes(term));
});

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;

    loadTags();
});

/**
 * Raw axios rather than the generated client: a registry that refuses answers with an
 * error the dialog should show, not a toast.
 */
function loadTags() {
    tags.value = [];
    error.value = null;
    isLoading.value = true;
    ApiService.apiAxios!.get(`/container-images/${props.input.containerImage.id}/tags`)
        .then((response) => {
            if (response.data?.status === "OK") {
                tags.value = response.data.resource?.tags ?? [];
            } else {
                error.value = String(response.data?.error ?? "The registry could not be read.");
            }
        })
        .catch((e) => (error.value = e?.message ?? "The registry could not be read."))
        .finally(() => (isLoading.value = false));
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onRefreshBtnClicked() {
    loadTags();
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>
</script>

<template>
    <v-dialog persistent height="80vh" width="40vw" min-width="480" v-model="showDialog">
        <v-card class="w-100 h-100">
            <v-card-title>Tags · {{ props.input.containerImage.name }}</v-card-title>
            <v-divider />
            <v-card-text>
                <div class="text-caption text-medium-emphasis mb-2">
                    From <code>{{ props.input.containerImage.url }}</code>
                </div>
                <v-alert v-if="error" density="compact" variant="tonal" type="error" class="mb-4">
                    {{ error }}
                </v-alert>
                <div v-if="tags.length" class="d-flex align-center ga-4 mb-2">
                    <v-text-field
                        v-model="search"
                        placeholder="Search tags"
                        prepend-inner-icon="fa fa-magnifying-glass"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                    />
                    <div class="text-body-2 text-medium-emphasis text-no-wrap">
                        {{ tags.length }} tag{{ tags.length === 1 ? "" : "s" }}
                    </div>
                </div>
                <v-progress-linear v-if="isLoading" indeterminate class="mb-2" />
                <div v-else-if="!error && !tags.length" class="text-body-2 text-medium-emphasis">
                    The registry answered, and has no tags for this image.
                </div>
                <v-list v-if="rows.length" density="compact" class="py-0">
                    <v-list-item v-for="tag in rows" :key="tag.name" :title="tag.name" class="px-0">
                        <template v-if="tag.pushed_at" v-slot:append>
                            <DateView :date-string="tag.pushed_at" text-format="DD/MM-YY HH:mm" class="text-body-2 text-medium-emphasis pushed-at" />
                        </template>
                    </v-list-item>
                </v-list>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>
                <v-btn variant="tonal" prepend-icon="fa fa-rotate" :loading="isLoading" @click="onRefreshBtnClicked"> Refresh </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
/* Same width for every date, so they line up: Roboto kerns around a 1. */
.pushed-at {
    font-variant-numeric: tabular-nums;
    font-kerning: none;
}
</style>
