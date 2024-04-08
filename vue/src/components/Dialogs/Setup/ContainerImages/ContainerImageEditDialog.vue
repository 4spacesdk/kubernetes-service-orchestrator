<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {ContainerImage} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {ContainerImageTagPolicies, ContainerRegistries} from "@/constants";

export interface ContainerImageEditDialog_Input {
    containerImage: ContainerImage;
}

const props = defineProps<{ input: ContainerImageEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<ContainerImage>(new ContainerImage());
const showPullSecret = ref(false);

const containerRegistries = ref([
    {
        identifier: ContainerRegistries.ArtifactContainerRegistry,
        name: "Artifact Container Registry",
    },
]);
const hasFile = ref(false);
const file = ref<File[]>();

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    load();
});

onUnmounted(() => {
});

function load() {
    if (props.input.containerImage.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.containerImages().getById(props.input.containerImage.id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
                render();
            });
    } else {
        item.value = props.input.containerImage;
        showDialog.value = true;
        render();
    }
}

function render() {
    showPullSecret.value = (item.value.pull_secret?.length ?? 0) > 0;
    hasFile.value = (item.value.registry_provider_credentials?.length ?? 0) > 0;
}

function close() {
    showDialog.value = false;
    bus.emit('containerImageEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    if (!showPullSecret.value) {
        item.value.pull_secret = '';
    }
    const api = item.value!.exists()
        ? Api.containerImages().patchById(item.value!.id!)
        : Api.containerImages().post();

    api.save(item.value!, newItem => {
        bus.emit('containerImageSaved', newItem);
        close();
    });

    close();
}

function onCloseBtnClicked() {
    close();
}

function onFileSelected() {
    const reader = new FileReader();
    reader.onload = file => {
        if (file.target?.result) {
            item.value.registry_provider_credentials = file.target.result as string;
            hasFile.value = true;
        }
    }
    reader.readAsText(file.value![0]!);
}

function onRemoveFileClicked() {
    file.value = [];
    hasFile.value = false;
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        height="60vh"
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading"
        >
            <v-card-title>Container Image</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            label="Name"/>
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.url"
                            label="Url"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col
                        cols="12"
                        class="mt-4"
                    >
                        <v-card
                            class="px-2"
                        >
                            <v-switch
                                v-model="showPullSecret"
                                variant="outlined"
                                label="Use image pull secret"
                                density="compact"
                                color="secondary"
                            />
                            <div
                                v-if="showPullSecret"
                            >
                                <v-row>
                                    <v-col
                                        cols="12"
                                    >
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.pull_secret"
                                            label="Image pull secret"
                                            density="compact"
                                            hide-details
                                        />
                                    </v-col>
                                </v-row>
                            </div>
                        </v-card>
                    </v-col>

                    <v-col
                        cols="12"
                        class="mt-4"
                    >
                        <v-card
                            class="px-2"
                        >
                            <v-switch
                                v-model="item.registry_subscribe"
                                variant="outlined"
                                label="Setup registry"
                                density="compact"
                                color="secondary"
                            />
                            <div
                                v-if="item.registry_subscribe"
                            >
                                <v-row>
                                    <v-col
                                        cols="12"
                                    >
                                        <v-select
                                            v-model="item.registry_provider"
                                            :items="containerRegistries"
                                            item-title="name"
                                            item-value="identifier"
                                            variant="outlined"
                                            label="Registry"
                                            density="compact"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col
                                        cols="12"
                                    >
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.registry_provider_project"
                                            label="Registry project/tenant"
                                            density="compact"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col
                                        cols="12"
                                    >
                                        <div
                                            class="d-flex"
                                        >
                                            <v-file-input
                                                v-model="file"
                                                variant="outlined"
                                                label="Credentials"
                                                hide-details
                                                accept="application/json"
                                                :disabled="hasFile"
                                                @change="onFileSelected"
                                            />
                                            <v-btn
                                                v-if="hasFile"
                                                @click="onRemoveFileClicked"
                                                size="sm"
                                                variant="text"
                                                class="my-auto ml-2"
                                                color="grey"
                                            >
                                                <v-icon>fa fa-trash</v-icon>
                                                <v-tooltip
                                                    activator="parent"
                                                    location="bottom"
                                                >
                                                    Remove credentials file
                                                </v-tooltip>
                                            </v-btn>
                                        </div>
                                    </v-col>
                                </v-row>
                            </div>
                        </v-card>
                    </v-col>
                </v-row>
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Close
                </v-btn>

                <v-btn
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="green"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
