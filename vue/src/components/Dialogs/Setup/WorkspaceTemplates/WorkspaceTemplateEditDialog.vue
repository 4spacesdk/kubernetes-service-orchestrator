<script setup lang="ts">
import { ReferenceData } from "@/core/referenceData";
import { dnsLabelRule } from "@/core/kubernetesNames";
import { useDialogSave } from "@/composables/useDialogSave";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DatabaseService, WorkspaceTemplate, Domain, EmailService, Project} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface WorkspaceTemplateCreateDialog_Input {
    workspaceTemplate: WorkspaceTemplate;
}

const props = defineProps<{ input: WorkspaceTemplateCreateDialog_Input, events: DialogEventsInterface }>();

const { form, isSaving, save } = useDialogSave();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<WorkspaceTemplate>(new WorkspaceTemplate());

const isLoadingEmailServices = ref(false);
const emailServiceItems = ref<EmailService[]>([]);
const isLoadingDatabaseServices = ref(false);
const databaseServiceItems = ref<DatabaseService[]>([]);
const isLoadingDomains = ref(false);
const domainItems = ref<Domain[]>([]);
const isLoadingProjects = ref(false);
const projectItems = ref<Project[]>([]);

const isFormValid = ref(false);
const rules = {
    required: [
        (value: any) => {
            if (value) {
                return true;
            }
            return 'Field is required';
        }
    ]
};

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
    if (props.input.workspaceTemplate.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.workspaceTemplates().getById(props.input.workspaceTemplate.id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
            });
    } else {
        item.value = props.input.workspaceTemplate;
        showDialog.value = true;
    }

    isLoadingEmailServices.value = true;
    ReferenceData.emailServices().then(response => {
            emailServiceItems.value = response;
            isLoadingEmailServices.value = false;
        });

    isLoadingDatabaseServices.value = true;
    ReferenceData.databaseServices().then(response => {
            databaseServiceItems.value = response;
            isLoadingDatabaseServices.value = false;
        });

    isLoadingDomains.value = true;
    ReferenceData.domains().then(response => {
            domainItems.value = response;
            isLoadingDomains.value = false;
        });

    isLoadingProjects.value = true;
    ReferenceData.projects().then(response => {
            projectItems.value = response;
            isLoadingProjects.value = false;
        });
}

function close() {
    showDialog.value = false;
    bus.emit('workspaceTemplateEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value!.exists() ? Api.workspaceTemplates().patchById(item.value!.id!) : Api.workspaceTemplates().post();

    save(api, item.value!, newItem => {
        bus.emit('workspaceTemplateSaved', newItem);
        close();
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
            ref="form"
            v-model="isFormValid"
            @submit.prevent
        >
            <v-card
                class="w-100 h-100">
                <v-card-title>Workspace Template</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row density="compact">
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                v-model="item.name"
                                label="Name"
                                :rules="rules.required"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                v-model="item.namespace"
                                label="Namespace"
                                :rules="[dnsLabelRule]"
                                hint="Starts every workspace's namespace: a-z, 0-9 and -"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-select
                                v-model="item.default_email_service_id"
                                :loading="isLoadingEmailServices"
                                :items="emailServiceItems"
                                item-title="name"
                                item-value="id"
                                variant="outlined"
                                label="Default Email Service"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-select
                                v-model="item.default_database_service_id"
                                :loading="isLoadingDatabaseServices"
                                :items="databaseServiceItems"
                                item-title="name"
                                item-value="id"
                                variant="outlined"
                                label="Default Database Service"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-select
                                v-model="item.default_domain_id"
                                :loading="isLoadingDomains"
                                :items="domainItems"
                                item-title="name"
                                item-value="id"
                                variant="outlined"
                                label="Default Domain"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-select
                                v-model="item.project_id"
                                :loading="isLoadingProjects"
                                :items="projectItems"
                                item-title="name"
                                item-value="id"
                                variant="outlined"
                                label="Project"
                                clearable
                                hint="Workspaces made from this template land in this project"
                                persistent-hint
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
                        :disabled="!isFormValid"
                        flat
                        variant="tonal"
                        prepend-icon="fa fa-check"
                        color="success"
                        :loading="isSaving"
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
