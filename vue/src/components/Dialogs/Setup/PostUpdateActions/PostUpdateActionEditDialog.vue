<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {PostUpdateAction} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {PostUpdateActionTypes} from "@/constants";
import {VTextField} from "vuetify/components/VTextField";
import PodioAppReferenceSelect from "@/components/Modules/Integrations/PodioIntegrations/PodioAppReferenceSelect.vue";

export interface PostUpdateActionEditDialog_Input {
    postUpdateAction: PostUpdateAction;

    onSaveCallback?: (item: PostUpdateAction) => void;
}

interface Argument {
    value: string;
}

const props = defineProps<{ input: PostUpdateActionEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<PostUpdateAction>(new PostUpdateAction());

const types = ref([
    {
        identifier: PostUpdateActionTypes.Podio_AddComment,
        name: "Podio: Add Comment",
    },
    {
        identifier: PostUpdateActionTypes.Podio_FieldUpdate,
        name: "Podio: Update Field",
    },
]);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

onUnmounted(() => {
});

function render() {
    if (props.input.postUpdateAction.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.postUpdateActions().getById(props.input.postUpdateAction.id!).find(items => {
            if (!items || items.length == 0) {
                return;
            }
            item.value = items[0];
            isLoading.value = false;
        });
    } else {
        item.value = props.input.postUpdateAction;
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('postUpdateActionEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value!.exists() ? Api.postUpdateActions().patchById(item.value!.id!) : Api.postUpdateActions().post();

    api.save(item.value!, newItem => {
        bus.emit('postUpdateActionSaved', newItem);

        if (props.input.onSaveCallback) {
            props.input.onSaveCallback(newItem);
        }
        close();
    });

    close();
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading">
            <v-card-title>Post Update Action</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            :rules="[
                                v => /^[a-z0-9-]{1,50}$/.test(v) || 'Invalid format'
                            ]"
                            label="Name"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="6">
                        <v-select
                            v-model="item.type"
                            :items="types"
                            item-title="name"
                            item-value="identifier"
                            variant="outlined"
                            label="Type"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col
                        v-if="item.type == PostUpdateActionTypes.Podio_AddComment"
                        cols="12"
                    >
                        <div>
                            <label>App Reference</label>
                            <podio-app-reference-select
                                v-model="item.podio_add_comment_integration_id"
                                class="mt-1"
                                label="Podio Integration"
                            />
                        </div>
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
