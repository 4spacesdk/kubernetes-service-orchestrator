<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DeploymentPackage, Domain, System, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface WorkspaceCreateDialog_Input {
    deploymentPackage: DeploymentPackage;
}

const props = defineProps<{ input: WorkspaceCreateDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isFormValid = ref(false);

const item = ref<Workspace | null>(null);
const isLoadingDomains = ref(false);
const domains = ref<Domain[]>([]);
const isSaving = ref(false);
const showAdvanced = ref(false);

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
    showDialog.value = true;

    item.value = Workspace.CreateDefault(props.input.deploymentPackage);

    isLoadingDomains.value = true;
    Api.domains().get()
        .find(items => {
            domains.value = items;
            isLoadingDomains.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

function generateNamespace() {
    let system = item.value!.name
        ?.toLocaleLowerCase() // To lowercase
        ?.replaceAll(' ', '_') // Replace space with _
        ?.replaceAll(',', '') // Remove ,
        ?.replace(/[^A-Za-z0-9_]/, '') // Remove all non-alphabetic
        ?.replaceAll('_', '-') // Replace _ with -
        ?? '';
    item.value!.namespace = `${props.input.deploymentPackage.namespace}-${system}`.substring(0, 63);
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onNameChanged() {
    generateNamespace();
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.workspaces().createPost()
        .deploymentPackageId(props.input.deploymentPackage.id!)
        .name(item.value!.name_readable!)
        .namespace(item.value!.namespace!)
        .domainId(item.value!.domain_id!)
        .subdomain(item.value!.subdomain!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save(item.value!, newItem => {
        if (newItem) {
            isSaving.value = false;
            bus.emit('workspaceSaved', newItem);
            close();
        }
    });
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
            v-model="isFormValid"
        >
            <v-card
                class="w-100 h-100">
                <v-card-title>Workspace: Create {{ props.input.deploymentPackage.name }}</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row
                        dense>
                        <v-col cols="12">
                            <v-text-field
                                v-model="item!.name_readable"
                                variant="outlined"
                                label="Name"
                                clearable
                                @update:modelValue="onNameChanged"
                                :rules="[
                                    v => (v?.length ?? 0) >= 4 || 'Must be at least four characters long'
                                ]"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-select
                                v-model="item!.domain_id"
                                :loading="isLoadingDomains"
                                :items="domains"
                                item-title="name"
                                item-value="id"
                                variant="outlined"
                                label="Domain"/>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                                v-model="item!.subdomain"
                                variant="outlined"
                                label="Subdomain"
                                clearable
                                :rules="[
                                    v => /^([a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?)$/.test(v) || 'Invalid format'
                                ]"
                                persistent-hint
                                hint="Max 63 characters, must begin with an alpha-numeric"/>
                        </v-col>
                    </v-row>
                    <v-row
                        class="mt-4"
                        dense
                    >
                        <v-col cols="12">
                            <v-btn
                                class="w-100"
                                @click="showAdvanced = !showAdvanced"
                                variant="flat"
                                density="compact"
                            >
                                <div class="d-flex ga-3">
                                    <v-icon
                                        v-if="showAdvanced"
                                        class="my-auto">fa fa-chevron-up
                                    </v-icon>
                                    <v-icon
                                        v-else
                                        class="my-auto">fa fa-chevron-down
                                    </v-icon>
                                    <span class="my-auto">Advanced</span>
                                    <v-icon
                                        v-if="showAdvanced"
                                        class="my-auto">fa fa-chevron-up
                                    </v-icon>
                                    <v-icon
                                        v-else
                                        class="my-auto">fa fa-chevron-down
                                    </v-icon>
                                </div>
                            </v-btn>
                        </v-col>
                    </v-row>
                    <v-expand-transition>
                        <v-row
                            v-if="showAdvanced"
                            dense>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="item.namespace"
                                    variant="outlined"
                                    :rules="[
                                    v => /^(?!^[0-9]*$)^([a-z0-9]([a-z0-9]|-(?!-)){0,14}(?<!-)$)/.test(v) || 'Invalid format'
                                ]"
                                    label="Namespace"
                                    clearable
                                    persistent-hint
                                    hint="Max 63 characters, lowercase-only"/>
                            </v-col>
                        </v-row>
                    </v-expand-transition>
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
                        :loading="isSaving"
                        :disabled="!isFormValid"
                        flat
                        variant="tonal"
                        prepend-icon="fa fa-check"
                        color="green"
                        @click="onSaveBtnClicked">
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-form>
    </v-dialog>
</template>

<style scoped>

</style>
