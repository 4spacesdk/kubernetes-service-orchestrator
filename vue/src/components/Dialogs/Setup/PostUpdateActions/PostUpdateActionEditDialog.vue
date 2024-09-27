<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {PodioFieldReference, PostUpdateAction} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {PostUpdateActionTypes} from "@/constants";
import {VTextField} from "vuetify/components/VTextField";
import PodioIntegrationSelect from "@/components/Modules/Integrations/PodioIntegrations/PodioIntegrationSelect.vue";
import PodioFieldReferenceSelect
    from "@/components/Modules/Integrations/PodioIntegrations/PodioFieldReferenceSelect.vue";
import PodioFieldValueInput from "@/components/Modules/Integrations/PodioIntegrations/PodioFieldValueInput.vue";

export interface PostUpdateActionEditDialog_Input {
    postUpdateAction: PostUpdateAction;

    onSaveCallback?: (item: PostUpdateAction) => void;
}

interface Variable {
    name: string;
    code: string;
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

const showVariablesMenu = ref(false);
const variables = ref<Variable[]>([
    {
        name: "Workspace Name",
        code: "${workspace.name}"
    },
    {
        name: "Commit URL",
        code: "${commit.url}"
    },
]);

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
    if (props.input.postUpdateAction.exists()) {
        isLoading.value = true;
        Api.postUpdateActions().getById(props.input.postUpdateAction.id!).find(items => {
            if (!items || items.length == 0) {
                return;
            }
            item.value = items[0];
            isLoading.value = false;
            render();
        });
    } else {
        item.value = props.input.postUpdateAction;
        render();
    }
}

function render() {
    if (!item.value.podio_field_update_field_reference) {
        item.value.podio_field_update_field_reference = new PodioFieldReference();
    }
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    bus.emit('postUpdateActionEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    if (!item.value.podio_field_update_field_reference?.podio_integration_id) {
        item.value.podio_field_update_field_reference = undefined;
    }
    if (!item.value.podio_add_comment_integration?.app_id) {
        item.value.podio_add_comment_integration = undefined;
    }

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

function onVariableClicked(variable: Variable) {
    item.value.podio_add_comment_value += variable.code;
    showVariablesMenu.value = false;
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
                        <podio-integration-select
                            v-model="item.podio_add_comment_integration_id"
                            class="mt-1"
                            label="Podio Integration"
                        />
                    </v-col>

                    <v-col
                        v-if="item.type == PostUpdateActionTypes.Podio_AddComment"
                        cols="12"
                    >
                        <div
                            class="d-flex"
                        >
                            <v-textarea
                                v-model="item.podio_add_comment_value"
                                variant="outlined"
                                label="Value"
                                spellcheck="false"
                                density="compact"
                                hide-details
                            />

                            <v-menu
                                v-model="showVariablesMenu"
                                :close-on-content-click="false"
                                left
                                min-width="250"
                                offset-y>
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        :icon="true"
                                        variant="plain"
                                        color="primary"
                                        size="small">
                                        <v-icon>fa fa-plus</v-icon>
                                        <v-tooltip activator="parent" location="bottom">Insert variable</v-tooltip>
                                    </v-btn>
                                </template>

                                <v-list
                                    class="list-items">
                                    <v-list-item
                                        v-for="(variable, i) in variables" :key="i"
                                        dense
                                        @click="onVariableClicked(variable)">
                                        <v-list-item-title>
                                            <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                                            <span class="ml-2">{{ variable.name }}</span>
                                        </v-list-item-title>
                                    </v-list-item>
                                </v-list>
                            </v-menu>
                        </div>
                    </v-col>

                    <v-col
                        v-if="item.type == PostUpdateActionTypes.Podio_FieldUpdate"
                        cols="12"
                    >
                        <podio-field-reference-select
                            v-model="item.podio_field_update_field_reference"
                            class="mt-1"
                            label="Podio Field"
                        />
                    </v-col>
                    <v-col
                        v-if="item.type == PostUpdateActionTypes.Podio_FieldUpdate && item.podio_field_update_field_reference"
                        cols="12"
                    >
                        <podio-field-value-input
                            v-model="item.podio_field_update_value"
                            class="mt-1"
                            label="New Value"
                            :field="item.podio_field_update_field_reference"
                        />
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
