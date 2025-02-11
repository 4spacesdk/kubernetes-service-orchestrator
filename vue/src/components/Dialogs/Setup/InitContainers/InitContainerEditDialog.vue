<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {ContainerImage, InitContainer} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {ContainerImageTagPolicies, ImagePullPolicies} from "@/constants";
import {VTextField} from "vuetify/components/VTextField";
import VariableBtn from "@/components/Modules/Common/VariableBtn.vue";

export interface InitContainerEditDialog_Input {
    initContainer: InitContainer;

    onSaveCallback?: (item: InitContainer) => void;
}

interface Argument {
    value: string;
}

const props = defineProps<{ input: InitContainerEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<InitContainer>(new InitContainer());
const args = ref<Argument[]>([]);

const containerImageItems = ref<ContainerImage[]>([]);
const isLoadingContainerImageItems = ref(false);
const containerImageTagPolicies = ref([
    {
        identifier: ContainerImageTagPolicies.MatchDeployment,
        name: "Match deployment",
    },
    {
        identifier: ContainerImageTagPolicies.Static,
        name: "Static",
    },
    {
        identifier: ContainerImageTagPolicies.Default,
        name: "Default",
    },
]);
const imagePullPolicies = ref([
    {
        identifier: ImagePullPolicies.IfNotPresent,
        name: "If not present",
    },
    {
        identifier: ImagePullPolicies.Always,
        name: "Always",
    },
    {
        identifier: ImagePullPolicies.Never,
        name: "Never",
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
    if (props.input.initContainer.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.initContainers().getById(props.input.initContainer.id!).find(items => {
            if (!items || items.length == 0) {
                return;
            }
            item.value = items[0];
            isLoading.value = false;
            args.value = JSON.parse(items[0].args ?? '[]')?.map((value: string) => {
                return {
                    value: value
                };
            });
        });
    } else {
        item.value = props.input.initContainer;
        showDialog.value = true;
    }

    isLoadingContainerImageItems.value = true;
    Api.containerImages().get()
        .orderAsc('name')
        .find(items => {
            containerImageItems.value = items;
            isLoadingContainerImageItems.value = false;
        });
}

function close() {
    showDialog.value = false;
    bus.emit('initContainerEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    item.value.args = JSON.stringify(args.value.map(value => value.value));

    const api = item.value!.exists() ? Api.initContainers().patchById(item.value!.id!) : Api.initContainers().post();

    api.save(item.value!, newItem => {
        bus.emit('initContainerSaved', newItem);

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

function onAddArgBtnClicked() {
    args.value.push({value: ''});
}

function onRemoveArgBtnClicked(index: number) {
    args.value.splice(index, 1);
}

function onCommandVariableAdded(value: string) {
    item.value.command += value;
}

function onArgVariableAdded(index: number, value: string) {
    args.value[index].value += value;
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
            <v-card-title>Init Container</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
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
                            v-model="item.container_image_id"
                            :loading="isLoadingContainerImageItems"
                            :items="containerImageItems"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            label="Container Image"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6">
                        <v-select
                            v-model="item.container_image_pull_policy"
                            :items="imagePullPolicies"
                            item-title="name"
                            item-value="identifier"
                            variant="outlined"
                            label="Image pull policy"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6">
                        <v-select
                            v-model="item.container_image_tag_policy"
                            :items="containerImageTagPolicies"
                            item-title="name"
                            item-value="identifier"
                            variant="outlined"
                            label="Tag policy"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="6"
                           v-if="item.container_image_tag_policy == ContainerImageTagPolicies.Static"
                    >
                        <v-text-field
                            v-model="item.container_image_tag_value"
                            variant="outlined"
                            label="Tag"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12">
                        <div
                            class="d-flex"
                        >
                            <v-text-field
                                variant="outlined"
                                v-model="item.command"
                                label="Command"
                                spellcheck="false"
                                density="compact"
                                hide-details
                            />

                            <variable-btn
                                @add-variable="onCommandVariableAdded($event)"
                            />

                            <v-btn
                                icon
                                variant="plain"
                                color="primary"
                                size="small"
                                @click="onAddArgBtnClicked()"
                            >
                                <v-icon>fa fa-plus</v-icon>
                                <v-tooltip activator="parent" location="bottom">Add arg</v-tooltip>
                            </v-btn>
                        </div>
                    </v-col>

                    <v-col
                        v-for="(arg, i) in args" :key="i"
                        cols="12"
                    >
                        <div
                            class="d-flex"
                        >
                            <v-text-field
                                variant="outlined"
                                v-model="arg.value"
                                spellcheck="false"
                                density="compact"
                                label="Arg"
                                hide-details
                            />
                            <variable-btn
                                @add-variable="onArgVariableAdded(i, $event)"
                            />
                            <v-btn
                                icon
                                variant="plain"
                                color="warning"
                                size="small"
                                @click="onRemoveArgBtnClicked(i)"
                            >
                                <v-icon>fa fa-minus</v-icon>
                                <v-tooltip activator="parent" location="bottom">Remove arg</v-tooltip>
                            </v-btn>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <v-switch
                            v-model="item.include_deployment_environment_variables"
                            variant="outlined"
                            label="Include deployment environment variables"
                            density="compact"
                            hide-details
                            color="secondary"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-switch
                            v-model="item.include_volumes"
                            variant="outlined"
                            label="Mount volumes"
                            density="compact"
                            hide-details
                            color="secondary"
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
