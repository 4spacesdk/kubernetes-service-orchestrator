<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DatabaseService, DeploymentPackage, Domain, EmailService} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentPackageCreateDialog_Input {
    deploymentPackage: DeploymentPackage;
}

const props = defineProps<{ input: DeploymentPackageCreateDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<DeploymentPackage>(new DeploymentPackage());

const isLoadingEmailServices = ref(false);
const emailServiceItems = ref<EmailService[]>([]);
const isLoadingDatabaseServices = ref(false);
const databaseServiceItems = ref<DatabaseService[]>([]);
const isLoadingDomains = ref(false);
const domainItems = ref<Domain[]>([]);

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
    if (props.input.deploymentPackage.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.deploymentPackages().getById(props.input.deploymentPackage.id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
            });
    } else {
        item.value = props.input.deploymentPackage;
        showDialog.value = true;
    }

    isLoadingEmailServices.value = true;
    Api.emailServices().get()
        .find(response => {
            emailServiceItems.value = response;
            isLoadingEmailServices.value = false;
        });

    isLoadingDatabaseServices.value = true;
    Api.databaseServices().get()
        .find(response => {
            databaseServiceItems.value = response;
            isLoadingDatabaseServices.value = false;
        });

    isLoadingDomains.value = true;
    Api.domains().get()
        .find(response => {
            domainItems.value = response;
            isLoadingDomains.value = false;
        });
}

function close() {
    showDialog.value = false;
    bus.emit('deploymentPackageEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value!.exists() ? Api.deploymentPackages().patchById(item.value!.id!) : Api.deploymentPackages().post();

    api.save(item.value!, newItem => {
        bus.emit('deploymentPackageSaved', newItem);
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
        <v-form
            v-model="isFormValid"
        >
            <v-card
                class="w-100 h-100">
                <v-card-title>Deployment Package</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row
                        dense>
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
                                :rules="rules.required"
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
                                :rules="rules.required"
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
                                :rules="rules.required"
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
                                :rules="rules.required"
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
