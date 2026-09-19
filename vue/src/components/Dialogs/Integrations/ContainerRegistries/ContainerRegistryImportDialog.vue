<script setup lang="ts">
import { ReferenceData } from "@/core/referenceData";
import { computed, onMounted, ref, watch } from "vue";
import { ContainerRegistry } from "@/core/services/Deploy/models";
import { Api, type ContainerRegistryRepository } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import ApiService from "@/services/ApiService";

/**
 * Create container images by picking repositories from a registry connection.
 * Opened from a connection, or from the image list without one - then it asks which.
 */
export interface ContainerRegistryImportDialog_Input {
    containerRegistry?: ContainerRegistry;
}

const props = defineProps<{ input: ContainerRegistryImportDialog_Input; events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const registries = ref<ContainerRegistry[]>([]);
const registryId = ref<number | undefined>(props.input.containerRegistry?.id);

const repositories = ref<ContainerRegistryRepository[]>([]);
const selected = ref<string[]>([]);
const isLoading = ref(false);
const isImporting = ref(false);
const error = ref<string | null>(null);

const search = ref("");

const headers = [
    { title: "Repository", key: "name", sortable: false },
    { title: "", key: "container_image_id", sortable: false, align: "end" as const },
];

/**
 * The host every url shares, shown once above the list rather than on every row.
 */
const host = computed(() => {
    const first = repositories.value.find((repository) => repository.url?.endsWith(`/${repository.name}`));
    return first ? first.url!.slice(0, -first.name!.length - 1) : "";
});

const importedCount = computed(() => repositories.value.filter((repository) => repository.container_image_id).length);

/**
 * What can be imported first, then what already is - both by name.
 */
const rows = computed(() => {
    const term = search.value?.toLowerCase() ?? "";
    return repositories.value
        .filter((repository) => !term || repository.name!.toLowerCase().includes(term))
        .sort((a, b) => Number(!!a.container_image_id) - Number(!!b.container_image_id) || a.name!.localeCompare(b.name!));
});

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;

    if (registryId.value) {
        loadRepositories();
    } else {
        ReferenceData.containerRegistries().then((items) => (registries.value = items));
    }
});

watch(registryId, () => loadRepositories());

/**
 * Raw axios rather than the generated client: a registry that refuses - an Azure principal
 * without catalog access, say - answers with an error the dialog should show, not a toast.
 */
function loadRepositories() {
    repositories.value = [];
    selected.value = [];
    error.value = null;
    if (!registryId.value) {
        return;
    }

    isLoading.value = true;
    ApiService.apiAxios!.get(`/container-registries/${registryId.value}/repositories`)
        .then((response) => {
            if (response.data?.status === "OK") {
                repositories.value = response.data.resources;
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

function onImportBtnClicked() {
    isImporting.value = true;
    Api.containerRegistries()
        .importPostById(registryId.value!)
        .save({ repositories: selected.value }, () => {
            isImporting.value = false;
            bus.emit("containerImageSaved", undefined);
            close();
        });
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>
</script>

<template>
    <v-dialog persistent height="80vh" width="60vw" min-width="720" v-model="showDialog">
        <v-card class="w-100 h-100">
            <v-card-title>Import container images</v-card-title>
            <v-divider />
            <v-card-text>
                <!-- A card holding a table loses its side padding (main.scss); this keeps its own. -->
                <div class="px-4 pt-4">
                    <v-select
                        v-if="!props.input.containerRegistry"
                        v-model="registryId"
                        :items="registries"
                        item-title="name"
                        item-value="id"
                        variant="outlined"
                        label="Registry connection"
                        density="compact"
                    />
                    <v-alert v-if="error" density="compact" variant="tonal" type="error" class="mb-4">
                        {{ error }}
                    </v-alert>
                    <div v-if="repositories.length" class="d-flex align-center ga-4 mb-2">
                        <v-text-field
                            v-model="search"
                            placeholder="Search repositories"
                            prepend-inner-icon="fa fa-magnifying-glass"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                        />
                        <div class="text-body-2 text-medium-emphasis text-no-wrap">
                            {{ repositories.length - importedCount }} to import · {{ importedCount }} already imported
                        </div>
                    </div>
                    <div v-if="host" class="text-caption text-medium-emphasis mb-2">
                        From <code>{{ host }}</code>
                    </div>
                </div>
                <v-data-table
                    v-model="selected"
                    :headers="headers"
                    :items="rows"
                    :loading="isLoading"
                    item-value="name"
                    :item-selectable="(item: ContainerRegistryRepository) => !item.container_image_id"
                    :items-per-page="-1"
                    show-select
                    density="compact"
                    no-data-text="No repositories"
                    hide-default-footer
                >
                    <template v-slot:item.name="{ item }">
                        <span :class="{ 'text-disabled': item.container_image_id }" :title="item.url">{{ item.name }}</span>
                    </template>
                    <template v-slot:item.container_image_id="{ item }">
                        <v-chip v-if="item.container_image_id" size="small" variant="tonal" prepend-icon="fa fa-check"> Imported </v-chip>
                    </template>
                </v-data-table>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>
                <v-btn
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-download"
                    color="green"
                    :disabled="selected.length == 0"
                    :loading="isImporting"
                    @click="onImportBtnClicked"
                >
                    {{ selected.length ? `Import ${selected.length}` : "Import" }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped></style>
