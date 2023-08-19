<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {ContainerImage, DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationEditDialog_Input {
    deploymentSpecification: DeploymentSpecification;
}

const props = defineProps<{ input: DeploymentSpecificationEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<DeploymentSpecification>(new DeploymentSpecification());

// Mandatory settings
const containerImageItems = ref<ContainerImage[]>([]);
const isLoadingContainerImageitems = ref(false);

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
    if (props.input.deploymentSpecification.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.deploymentSpecifications().getById(props.input.deploymentSpecification.id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
            });
    } else {
        item.value = props.input.deploymentSpecification;
        showDialog.value = true;
    }

    isLoadingContainerImageitems.value = true;
    Api.containerImages().get()
        .orderAsc('name')
        .find(items => {
            containerImageItems.value = items;
            isLoadingContainerImageitems.value = false;
        });
}

function close() {
    showDialog.value = false;
    bus.emit('deploymentSpecificationEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value!.exists() ? Api.deploymentSpecifications().patchById(item.value!.id!) : Api.deploymentSpecifications().post();

    api.save(item.value!, newItem => {
        bus.emit('deploymentSpecificationSaved', newItem);
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
        height="60vh"
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading">
            <v-card-title>Deployment Specification</v-card-title>
            <v-divider/>
            <v-card-text
                class="mb-4"
            >
                <v-row
                    dense>
                    <v-col cols="6">
                        <v-select
                            v-model="item.container_image_id"
                            :loading="isLoadingContainerImageitems"
                            :items="containerImageItems"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            label="Container Image"/>
                    </v-col>

                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="item.git_repo"
                            label="Git Repository Name"/>
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            :rules="[
                                v => /^[a-z0-9-]{1,50}$/.test(v) || 'Invalid format'
                            ]"
                            label="Name"/>
                    </v-col>

                    <v-col cols="6">
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_database"
                                label="Database"
                                hide-details
                            />
                            <div
                                v-if="item.enable_database"
                                class="px-2"
                            >
                                <v-row>
                                    <v-col cols="12">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.database_migration_command"
                                            label="Database migration command"
                                            hint="cd /var/www/html/ci4 && php spark migrate"
                                            persistent-hint
                                        />
                                    </v-col>
                                </v-row>
                            </div>
                        </v-card>
                    </v-col>

                    <v-col cols="6">
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_cronjob"
                                label="Cronjob"
                                hide-details
                            />
                            <div
                                v-if="item.enable_cronjob"
                                class="px-2"
                            >
                                <v-row>
                                    <v-col cols="12">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.cronjob_url"
                                            label="Cronjob Url"
                                            hint="/api/jobby"
                                            persistent-hint
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
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_ingress"
                                label="Ingress"
                                hide-details
                            />
                            <div
                                v-if="item.enable_ingress"
                                class="px-2"
                            >
                                <v-row>
                                    <v-col cols="6">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.domain_tls"
                                            label="TLS"
                                            hint="http, https, ws, wss"
                                            persistent-hint
                                        />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.domain_prefix"
                                            label="Domain prefix"
                                            hint="api-, app-"
                                            persistent-hint
                                        />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.domain_suffix"
                                            label="Domain suffix"
                                            hint="/products, /users"
                                            persistent-hint
                                        />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.domain_aliases"
                                            label="Aliases"
                                            persistent-hint
                                            hint="Comma-seperated: itk,1tk"/>
                                    </v-col>
                                </v-row>
                            </div>
                        </v-card>
                    </v-col>

                    <v-col
                        cols="12"
                        class="mt-4"
                    >
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_rbac"
                                label="RBAC"
                                hide-details
                            />
                            <div
                                v-if="item.enable_rbac"
                                class="px-2"
                            >
                                <v-row>
                                    <v-col cols="12">
                                        <span>Rules can be added from the settings button</span>
                                    </v-col>
                                </v-row>
                            </div>
                        </v-card>
                    </v-col>

                    <v-col
                        cols="12"
                        class="mt-4"
                    >
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_volumes"
                                label="Volumes"
                                hide-details
                            />
                            <div
                                v-if="item.enable_volumes"
                                class="px-2"
                            >
                                <v-row>
                                    <v-col cols="12">
                                        <span>Volumes can be added to deployments</span>
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
