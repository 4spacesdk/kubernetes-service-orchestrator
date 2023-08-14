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

const item = ref<Workspace | null>(null);
const isLoadingDomains = ref(false);
const domains = ref<Domain[]>([]);
const isSaving = ref(false);

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

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.workspaces().createPost()
        .deploymentPackageId(props.input.deploymentPackage.id!)
        .name(item.value!.name_readable!)
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
                            clearable/>
                    </v-col>
                    <v-col cols="12">
                        <v-select
                            v-model="item!.domain_id"
                            :loading="isLoadingDomains"
                            :items="domains"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            label="Domains"/>
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            v-model="item!.subdomain"
                            variant="outlined"
                            label="Subdomain"
                            clearable
                            :rules="[
                                v => /[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?/.test(v) || 'Invalid format'
                            ]"
                            persistent-hint
                            hint="Max 63 characters, must begin with an alpha-numeric"/>
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
                    :loading="isSaving"
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
