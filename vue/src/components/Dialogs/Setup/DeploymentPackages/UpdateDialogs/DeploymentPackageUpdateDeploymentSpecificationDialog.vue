<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Api} from "@/core/services/Deploy/Api";
import {DeploymentSpecification} from "@/core/services/Deploy/models";

export interface DeploymentPackageUpdateDeploymentSpecificationDialog_Input {
    settings: {
        deploymentSpecification: DeploymentSpecification,

        defaultEnablePodioNotification?: boolean,
        defaultVersion?: string,
        defaultAutoUpdateEnabled?: boolean,
        defaultAutoUpdateTagRegex?: string,
        defaultAutoUpdateRequireApproval?: boolean,
        defaultEnvironment?: string;
        defaultCpuRequest?: number;
        defaultCpuLimit?: number;
        defaultMemoryRequest?: number;
        defaultMemoryLimit?: number;
        defaultReplicas?: number;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: DeploymentPackageUpdateDeploymentSpecificationDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const showDialog = ref(false);
const formIsValid = ref<boolean>();

const defaultEnablePodioNotification = ref<boolean>();
const specifyDefaultVersion = ref<boolean>();
const defaultVersion = ref<string>();
const defaultAutoUpdateEnabled = ref<boolean>();
const defaultAutoUpdateTagRegex = ref<string>();
const defaultAutoUpdateRequireApproval = ref<boolean>();
const defaultEnvironment = ref<string>();
const defaultCpuRequest = ref<number>();
const defaultCpuLimit = ref<number>();
const defaultMemoryRequest = ref<number>();
const defaultMemoryLimit = ref<number>();
const defaultReplicas = ref<number>();

const isLoadingVersionTags = ref(false);
const versionTagItems = ref<string[]>([]);

const environmentItems = ref<string[]>([]);
const isLoadingEnvironments = ref(false);

const required = ref([
    (value: any) => {
        if (value) {
            return true;
        }
        return 'Field is required';
    }
]);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    watch(specifyDefaultVersion, loadVersionTags);

    render();
});

onUnmounted(() => {
});

function render() {
    defaultEnablePodioNotification.value = props.input.settings.defaultEnablePodioNotification ?? false;
    defaultVersion.value = props.input.settings.defaultVersion ?? '';
    specifyDefaultVersion.value = (defaultVersion.value?.length ?? 0) > 0;
    defaultAutoUpdateEnabled.value = props.input.settings.defaultAutoUpdateEnabled ?? false;
    defaultAutoUpdateTagRegex.value = props.input.settings.defaultAutoUpdateTagRegex ?? '';
    defaultAutoUpdateRequireApproval.value = props.input.settings.defaultAutoUpdateRequireApproval ?? false;
    defaultEnvironment.value = props.input.settings.defaultEnvironment ?? '';
    defaultCpuRequest.value = props.input.settings.defaultCpuRequest;
    defaultCpuLimit.value = props.input.settings.defaultCpuLimit;
    defaultMemoryRequest.value = props.input.settings.defaultMemoryRequest;
    defaultMemoryLimit.value = props.input.settings.defaultMemoryLimit;
    defaultReplicas.value = props.input.settings.defaultReplicas;

    loadVersionTags();

    isLoadingEnvironments.value = true;
    Api.environments().getGet()
        .find(response => {
            environmentItems.value = response.map(item => item.name!);
            isLoadingEnvironments.value = false;
        });

    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

function loadVersionTags() {
    if (specifyDefaultVersion.value) {
        isLoadingVersionTags.value = true;
        Api.deploymentSpecifications().getTagsGetById(props.input.settings.deploymentSpecification.id!)
            .find(items => {
                versionTagItems.value = items[0].tags ?? [];
                isLoadingVersionTags.value = false;
            });
    }
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.settings.defaultEnablePodioNotification = defaultEnablePodioNotification.value;
    props.input.settings.defaultVersion = defaultVersion.value;
    props.input.settings.defaultAutoUpdateEnabled = defaultAutoUpdateEnabled.value;
    props.input.settings.defaultAutoUpdateTagRegex = defaultAutoUpdateTagRegex.value;
    props.input.settings.defaultAutoUpdateRequireApproval = defaultAutoUpdateRequireApproval.value;
    props.input.settings.defaultEnvironment = defaultEnvironment.value;
    props.input.settings.defaultCpuRequest = defaultCpuRequest.value;
    props.input.settings.defaultCpuLimit = defaultCpuLimit.value;
    props.input.settings.defaultMemoryRequest = defaultMemoryRequest.value;
    props.input.settings.defaultMemoryLimit = defaultMemoryLimit.value;
    props.input.settings.defaultReplicas = defaultReplicas.value;
    props.input.onSaveCallback();
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
        <v-form
            v-model="formIsValid"
        >
            <v-card
                class="w-100 h-100"
            >
                <v-card-title>Deployment Specification Settings</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row>
                        <v-col cols="12">
                            <v-checkbox
                                v-model="defaultEnablePodioNotification"
                                variant="outlined"
                                label="Default Enable Podio Notification"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-card>
                                <v-checkbox
                                    v-model="specifyDefaultVersion"
                                    variant="outlined"
                                    label="Specify default version"
                                />
                                <div
                                    v-if="specifyDefaultVersion"
                                    class="px-2"
                                >
                                    <v-row>
                                        <v-col cols="12">
                                            <v-select
                                                v-model="defaultVersion"
                                                variant="outlined"
                                                label="Default Version"
                                                :rules="required"
                                                :items="versionTagItems"
                                                :loading="isLoadingVersionTags"
                                            />
                                        </v-col>
                                    </v-row>
                                </div>
                            </v-card>
                        </v-col>

                        <v-col cols="12">
                            <v-card
                                class="px-2"
                            >
                                <v-switch
                                    v-model="defaultAutoUpdateEnabled"
                                    variant="outlined"
                                    label="Auto update"
                                    color="secondary"
                                    hide-details
                                />
                                <div
                                    v-if="defaultAutoUpdateEnabled"
                                >
                                    <v-row>
                                        <v-col cols="6">
                                            <v-text-field
                                                v-model="defaultAutoUpdateTagRegex"
                                                variant="outlined"
                                                label="Tag regex"
                                                density="compact"
                                                hide-details
                                            />
                                        </v-col>
                                        <v-col cols="6">
                                            <v-switch
                                                v-model="defaultAutoUpdateRequireApproval"
                                                variant="outlined"
                                                label="Require approval"
                                                density="compact"
                                                hide-details
                                                color="secondary"
                                            />
                                        </v-col>
                                    </v-row>
                                </div>
                            </v-card>
                        </v-col>

                        <v-col cols="12">
                            <v-select
                                v-model="defaultEnvironment"
                                :loading="isLoadingEnvironments"
                                :items="environmentItems"
                                variant="outlined"
                                label="Default Environment"
                                :rules="required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="defaultCpuRequest"
                                type="number"
                                variant="outlined"
                                label="Default CPU Request"
                                hint="100 = 0.1 CPU"
                                persistent-hint
                                :rules="required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="defaultCpuLimit"
                                type="number"
                                variant="outlined"
                                label="Default CPU Limit"
                                :rules="required"
                                hint="500 = 0.5 CPU"
                                persistent-hint
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="defaultMemoryRequest"
                                type="number"
                                variant="outlined"
                                label="Default Memory Request"
                                hint="190.73 = 200 MB"
                                persistent-hint
                                :rules="required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="defaultMemoryLimit"
                                type="number"
                                variant="outlined"
                                label="Default Memory Limit"
                                hint="953.67 = 1 GB"
                                persistent-hint
                                :rules="required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                v-model.number="defaultReplicas"
                                type="number"
                                variant="outlined"
                                label="Default Replicas"
                                hint="0 = not running, 1 = testing purposes, 3 = production"
                                persistent-hint
                                :rules="required"
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
                        Done
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-form>
    </v-dialog>
</template>

<style scoped>

</style>
