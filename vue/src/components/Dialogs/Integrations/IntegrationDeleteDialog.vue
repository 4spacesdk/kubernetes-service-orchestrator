<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { ContainerImage } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";

/**
 * Deleting an integration the container images point at - a GitHub integration or a
 * container registry. The API refuses while any image uses it, so they are listed with a
 * way to move each one first.
 */
export interface IntegrationDeleteDialog_Input {
    name: string;
    /** The column on container_images that points at the integration */
    imageField: "github_integration_id" | "container_registry_id";
    id: number;
    /** Shown under each image's name */
    imageDetail: (image: ContainerImage) => string | undefined;
    note?: string;
    delete: (done: () => void) => void;
}

const props = defineProps<{ input: IntegrationDeleteDialog_Input; events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const images = ref<ContainerImage[]>([]);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;
    loadImages();
    bus.on("containerImageSaved", loadImages);
});

onUnmounted(() => {
    bus.off("containerImageSaved", loadImages);
});

function loadImages() {
    isLoading.value = true;
    Api.containerImages()
        .get()
        .where(props.input.imageField, props.input.id)
        .orderAsc("name")
        .find(items => {
            images.value = items;
            isLoading.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onEditImageBtnClicked(image: ContainerImage) {
    bus.emit("containerImageEdit", { containerImage: image });
}

function onDeleteBtnClicked() {
    props.input.delete(() => close());
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>
</script>

<template>
    <v-dialog persistent width="40vw" min-width="560" v-model="showDialog">
        <v-card class="w-100" :loading="isLoading">
            <v-card-title>Delete {{ input.name }}</v-card-title>
            <v-divider />
            <v-card-text>
                <template v-if="images.length">
                    <v-alert density="compact" variant="tonal" type="warning" class="mb-2">
                        {{ images.length == 1 ? "1 container image uses it. Move it" : `${images.length} container images use it. Move them` }}
                        elsewhere before deleting it.
                    </v-alert>
                    <v-list density="compact">
                        <v-list-item v-for="image in images" :key="image.id" :title="image.name">
                            <template v-slot:subtitle>
                                <span :title="input.imageDetail(image)">{{ input.imageDetail(image) }}</span>
                            </template>
                            <template v-slot:append>
                                <v-btn variant="plain" color="primary" size="small" @click="onEditImageBtnClicked(image)">
                                    <v-icon>fa fa-pen</v-icon>
                                    <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                                </v-btn>
                            </template>
                        </v-list-item>
                    </v-list>
                </template>
                <div v-else-if="!isLoading">
                    Do you want to delete <strong>{{ input.name }}</strong>?
                    <template v-if="input.note">{{ input.note }}</template>
                </div>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>
                <v-btn variant="tonal" color="red" prepend-icon="fa fa-trash" :disabled="isLoading || images.length > 0" @click="onDeleteBtnClicked">
                    Delete
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped></style>
