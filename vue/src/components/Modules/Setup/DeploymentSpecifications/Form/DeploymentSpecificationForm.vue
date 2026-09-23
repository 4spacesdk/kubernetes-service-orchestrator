<script setup lang="ts">
import {ReferenceData} from "@/core/referenceData";
import {onMounted, ref, watch} from 'vue'
import {ContainerImage, DeploymentSpecification, System} from "@/core/services/Deploy/models";
import bus from "@/plugins/bus";
import {ContainerImageTagPolicies, MigrationVerificationTypes, NetworkTypes, WorkloadTypes} from "@/constants";
import CodeEditor from 'simple-code-editor';
import VariableBtn from "@/components/Modules/Common/VariableBtn.vue";

/**
 * A deployment specification's own fields. Used by the create dialog and by General on the
 * specification's page. Everything it is made of beyond these is a section of that page.
 */
const props = defineProps<{
    item: DeploymentSpecification;
}>();

const formRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);

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

onMounted(() => {
    renderIsCustomMigrationImage();
    watch(() => props.item, () => renderIsCustomMigrationImage());

    isLoadingContainerImageItems.value = true;
    ReferenceData.containerImages().then(items => {
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
    if (System.Instance.is_network_gateway_api_supported) {
        networkTypes.value.push({
            identifier: NetworkTypes.GatewayApi,
            name: 'Gateway Api',
        });
    }
});

function renderIsCustomMigrationImage() {
    isCustomMigrationImage.value = (props.item.database_migration_container_image_id ?? 0) > 0
        && props.item.container_image_id != props.item.database_migration_container_image_id;
}

function onVariableClicked(text: string) {
    navigator.clipboard.writeText(text);
    bus.emit('toast', {
        text: `Variables copied to clipboard`
    });
}

/** The form's rules. Bound as `ref="form"` for useDialogSave. */
async function validate(): Promise<{ valid: boolean }> {
    return formRef.value ? formRef.value.validate() : {valid: true};
}

/** Clears what the form hides, so it is not saved. Call before saving. */
function prepareForSave() {
    if (!isCustomMigrationImage.value) {
        props.item.database_migration_container_image_id = 0;
        props.item.database_migration_container_image = undefined;
    }
}

defineExpose({validate, prepareForSave});

</script>

<template>
    <v-form ref="formRef" @submit.prevent>
        <v-row density="compact"
        >
            <v-col cols="12">
                <v-text-field
                    variant="outlined"
                    v-model="item.name"
                    :rules="[
                        v => /^[a-z0-9-]{1,50}$/.test(v) || 'Invalid format'
                    ]"
                    label="Name"/>
            </v-col>

            <v-col
                v-if="item.workload_type == WorkloadTypes.Deployment || item.workload_type == WorkloadTypes.KNativeService || item.workload_type == WorkloadTypes.DaemonSet"
                cols="12"
            >
                <v-select
                    v-model="item.container_image_id"
                    :loading="isLoadingContainerImageItems"
                    :items="containerImageItems"
                    item-title="name"
                    item-value="id"
                    variant="outlined"
                    :rules="[v => !!v || 'Required']"
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
                            <v-col
                                v-if="item.network_type == NetworkTypes.GatewayApi"
                                cols="6">
                                <v-text-field
                                    variant="outlined"
                                    type="number"
                                    v-model.number="item.gateway_backend_timeout"
                                    label="Backend timeout (seconds)"
                                    persistent-hint
                                    hint="GKE Gateway only. Empty keeps the GCP default of 30s"/>
                            </v-col>
                        </v-row>
                    </div>
                </v-card>
            </v-col>

            <v-col
                v-if="item.workload_type == WorkloadTypes.Deployment || item.workload_type == WorkloadTypes.DaemonSet || item.workload_type == WorkloadTypes.CustomResource"
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
                                <span>Rules are added under RBAC once this is saved</span>
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
                                <span>Volumes are added under Volumes once this is saved, or directly to deployments</span>
                            </v-col>
                        </v-row>
                    </div>
                </v-card>
            </v-col>

            <v-col cols="12">
                <div
                    style="position: relative"
                    v-if="item.workload_type == WorkloadTypes.CustomResource"
                >
                    <CodeEditor
                        v-model="item.custom_resource"
                        :languages="[['yaml']]"
                        width="100%"
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

    </v-form>
</template>

<style scoped>
.code-editor {
    letter-spacing: 0 !important;
    line-height: 0 !important;
}
</style>
