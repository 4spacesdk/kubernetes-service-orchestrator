<script setup lang="ts">
import { ReferenceData } from "@/core/referenceData";
import { dnsLabelRule, toDnsLabel } from "@/core/kubernetesNames";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {WorkspaceTemplate, Domain, System, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {useJustCreated} from "@/composables/useJustCreated";

export interface WorkspaceCreateDialog_Input {
    workspaceTemplate: WorkspaceTemplate;
    /**
     * The project whose list it is created from - `none` for the list of those in no project. It
     * lands there. Left out, as from the list of every workspace, in its template's project.
     */
    project?: number | "none";
}

const props = defineProps<{ input: WorkspaceCreateDialog_Input, events: DialogEventsInterface }>();

const {workspaceIds: justCreated} = useJustCreated();

const used = ref(false);
const showDialog = ref(false);
const isFormValid = ref(false);

const item = ref<Workspace | null>(null);
const isLoadingDomains = ref(false);
const domains = ref<Domain[]>([]);
const isSaving = ref(false);
const showAdvanced = ref(false);
/** The project the workspace lands in: the one it is created from, or else its template's. */
const projectId = computed<number | null>(() => {
    if (props.input.project === "none") {
        return null;
    }
    return props.input.project ?? props.input.workspaceTemplate.project_id ?? null;
});
const projectName = ref<string | null>(null);

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

    item.value = Workspace.CreateDefault(props.input.workspaceTemplate);

    if (projectId.value) {
        ReferenceData.projects().then(items => {
            projectName.value = items.find(project => project.id == projectId.value)?.name ?? null;
        });
    }

    isLoadingDomains.value = true;
    ReferenceData.domains().then(items => {
            domains.value = items;
            isLoadingDomains.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

/**
 * The template's namespace and the name, as a DNS label. It used to drop only the first
 * character Kubernetes does not accept, so "Øster Ås" left an "å" behind.
 */
function generateNamespace() {
    item.value!.namespace = toDnsLabel(`${props.input.workspaceTemplate.namespace ?? ''}-${item.value!.name_readable ?? ''}`);
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onNameChanged() {
    generateNamespace();
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.workspaces().createPost()
        .workspaceTemplateId(props.input.workspaceTemplate.id!)
        .name(item.value!.name_readable!)
        .namespace(item.value!.namespace!)
        .domainId(item.value!.domain_id!)
        .subdomain(item.value!.subdomain!);
    if (props.input.project !== undefined) {
        api.projectId(projectId.value ?? 0);
    }
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
            justCreated.value.push(newItem.id!);
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
                <v-card-title>Workspace: Create {{ props.input.workspaceTemplate.name }}</v-card-title>
                <v-divider/>
                <v-card-text>
                    <div
                        v-if="projectName"
                        class="text-body-medium text-medium-emphasis mb-3">
                        <v-icon size="12" class="mr-1">fa fa-folder</v-icon>
                        Lands in the project {{ projectName }}
                    </div>
                    <v-row density="compact">
                        <v-col cols="12">
                            <v-text-field
                                v-model="item!.name_readable"
                                variant="outlined"
                                label="Name"
                                clearable
                                @update:modelValue="onNameChanged"
                                :rules="[
                                    v => (v?.length ?? 0) >= 4 || 'Must be at least four characters long',
                                    v => /^\S(.*\S)?$/.test(v ?? '') || 'Cannot start or end with a space'
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
                        class="mt-4" density="compact"
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
                            v-if="showAdvanced" density="compact">
                            <v-col cols="6">
                                <v-text-field
                                    v-model="item!.namespace"
                                    variant="outlined"
                                    :rules="[dnsLabelRule]"
                                    label="Namespace"
                                    clearable
                                    persistent-hint
                                    hint="Max 63 characters: a-z, 0-9 and -"/>
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
                        color="success"
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
