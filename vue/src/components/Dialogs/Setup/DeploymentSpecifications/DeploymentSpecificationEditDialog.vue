<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {ContainerImage, DeploymentSpecification, System} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {ContainerImageTagPolicies, MigrationVerificationTypes, NetworkTypes, WorkloadTypes} from "@/constants";
import CodeEditor from 'simple-code-editor';
import VariableBtn from "@/components/Modules/Common/VariableBtn.vue";

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
const isLoadingContainerImageItems = ref(false);

const isCustomMigrationImage = ref(false);
const migrationTagPolicies = ref([
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

const migrationVerificationTypes = ref([
    {
        identifier: MigrationVerificationTypes.EndsWith,
        name: 'Ends With',
    },
    {
        identifier: MigrationVerificationTypes.Regex,
        name: "Regex",
    },
]);

const networkTypes = ref<{
    identifier: string,
    name: string,
}[]>([]);

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
                renderIsCustomMigrationImage();
            });
    } else {
        item.value = props.input.deploymentSpecification;
        showDialog.value = true;
        renderIsCustomMigrationImage();
    }

    isLoadingContainerImageItems.value = true;
    Api.containerImages().get()
        .orderAsc('name')
        .find(items => {
            containerImageItems.value = items;
            isLoadingContainerImageItems.value = false;
        });

    if (System.Instance.is_network_nginx_ingress_supported) {
        networkTypes.value.push({
            identifier: NetworkTypes.NginxIngress,
            name: 'Nginx Ingress',
        });
    }
    if (System.Instance.is_network_istio_supported) {
        networkTypes.value.push({
            identifier: NetworkTypes.Istio,
            name: 'Istio',
        });
    }
    if (System.Instance.is_network_contour_supported) {
        networkTypes.value.push({
            identifier: NetworkTypes.Contour,
            name: 'Contour',
        });
    }
}

function renderIsCustomMigrationImage() {
    isCustomMigrationImage.value = (item.value.database_migration_container_image_id ?? 0) > 0
        && item.value.container_image_id != item.value.database_migration_container_image_id;
}

function close() {
    showDialog.value = false;
    bus.emit('deploymentSpecificationEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    if (!isCustomMigrationImage.value) {
        item.value.database_migration_container_image_id = 0;
        item.value.database_migration_container_image = undefined;
    }

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

function onVariableClicked(text: string) {
    navigator.clipboard.writeText(text);
    bus.emit('toast', {
        text: `Variables copied to clipboard`
    });
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
                    dense
                >

                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            :rules="[
                                v => /^[a-z0-9-]{1,50}$/.test(v) || 'Invalid format'
                            ]"
                            label="Name"/>

                        <div
                            style="position: relative"
                            v-if="item.workload_type == WorkloadTypes.CustomResource"
                        >
                            <CodeEditor
                                v-model="item.custom_resource"
                                :languages="[['yaml']]"
                                class="w-100"
                                height="500px"
                                theme="atom-one-dark"
                                font-size="13px"
                                :line-nums="true"
                            />

                            <div
                                style="position: absolute; top: -2px; right: 40px;"
                            >
                                <variable-btn
                                    color="grey"
                                    @add-variable="newText => onVariableClicked(newText)"
                                />
                            </div>
                        </div>
                    </v-col>

                </v-row>

                <v-row
                    v-if="item.workload_type == WorkloadTypes.Deployment || item.workload_type == WorkloadTypes.KNativeService || item.workload_type == WorkloadTypes.DaemonSet"
                    dense
                >
                    <v-col cols="12">
                        <v-select
                            v-model="item.container_image_id"
                            :loading="isLoadingContainerImageItems"
                            :items="containerImageItems"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            label="Container Image"/>
                    </v-col>

                    <v-col cols="12">
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
                                        <v-switch
                                            v-model="isCustomMigrationImage"
                                            variant="outlined"
                                            label="Use different container image for migration"
                                            density="compact"
                                            hide-details
                                            color="secondary"
                                        />
                                    </v-col>
                                    <v-col cols="12"
                                           v-if="isCustomMigrationImage"
                                    >
                                        <v-select
                                            v-model="item.database_migration_container_image_id"
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
                                    <v-col cols="12"
                                           v-if="isCustomMigrationImage"
                                    >
                                        <v-row>
                                            <v-col cols="6">
                                                <v-select
                                                    v-model="item.database_migration_container_image_tag_policy"
                                                    :items="migrationTagPolicies"
                                                    item-title="name"
                                                    item-value="identifier"
                                                    variant="outlined"
                                                    label="Tag policy"
                                                    density="compact"
                                                    hide-details
                                                />
                                            </v-col>
                                            <v-col cols="6"
                                                   v-if="item.database_migration_container_image_tag_policy == ContainerImageTagPolicies.Static"
                                            >
                                                <v-text-field
                                                    v-model="item.database_migration_container_image_tag_value"
                                                    variant="outlined"
                                                    label="Tag"
                                                    density="compact"
                                                    hide-details
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-col>

                                    <v-col cols="12">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.database_migration_command"
                                            label="Migration command"
                                            hint="cd /var/www/html/ci4 && php spark migrate"
                                            persistent-hint
                                        />
                                    </v-col>

                                    <v-col cols="12">
                                        <v-row>
                                            <v-col cols="6">
                                                <v-select
                                                    v-model="item.database_migration_verification_type"
                                                    :items="migrationVerificationTypes"
                                                    item-title="name"
                                                    item-value="identifier"
                                                    variant="outlined"
                                                    label="Migration verification type"
                                                    density="compact"
                                                />
                                            </v-col>
                                            <v-col cols="6">
                                                <v-text-field
                                                    v-model="item.database_migration_verification_value"
                                                    variant="outlined"
                                                    label="Verification value"
                                                    density="compact"
                                                    persistent-hint
                                                    hint="Ex. Done. or (?:[a-z0-9\-]{0,61})?"
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-col>
                                </v-row>
                            </div>
                        </v-card>
                    </v-col>

                    <v-col cols="12"
                           class="mt-4"
                    >
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_cronjob"
                                label="Cronjob"
                                hide-details
                            />
                        </v-card>
                    </v-col>

                    <v-col
                        cols="12"
                        class="mt-4"
                    >
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_external_access"
                                label="External access"
                                hide-details
                            />
                            <div
                                v-if="item.enable_external_access"
                                class="px-2"
                            >
                                <v-row>
                                    <v-col cols="6">
                                        <v-select
                                            v-model="item.network_type"
                                            :items="networkTypes"
                                            item-title="name"
                                            item-value="identifier"
                                            variant="outlined"
                                            label="Network type"
                                            density="compact"
                                        />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field
                                            variant="outlined"
                                            v-model="item.domain_tls"
                                            label="Protocol"
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
                                    <v-col
                                        v-if="item.network_type == NetworkTypes.NginxIngress"
                                        cols="6">
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
                        v-if="item.workload_type == WorkloadTypes.Deployment || item.workload_type == WorkloadTypes.DaemonSet"
                        cols="12"
                        class="mt-4"
                    >
                        <v-card>
                            <v-checkbox
                                v-model="item.enable_internal_access"
                                label="Internal access"
                                hide-details
                            />
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
.code-editor {
    letter-spacing: 0 !important;
    line-height: 0 !important;
}
</style>
