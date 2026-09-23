<script setup lang="ts">
import { useDialogSave } from "@/composables/useDialogSave";
import { onMounted, ref } from "vue";
import { ContainerRegistry } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import { ContainerRegistries } from "@/constants";
import ApiService from "@/services/ApiService";

export interface ContainerRegistryEditDialog_Input {
    containerRegistry: ContainerRegistry;
}

const props = defineProps<{ input: ContainerRegistryEditDialog_Input; events: DialogEventsInterface }>();

const { isSaving, save } = useDialogSave();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<ContainerRegistry>(new ContainerRegistry());

const testResult = ref<{ ok: boolean; message: string } | null>(null);
const isTesting = ref(false);
const isSettingUpEvents = ref(false);

const providers = [
    { identifier: ContainerRegistries.ArtifactContainerRegistry, name: "Artifact Container Registry" },
    { identifier: ContainerRegistries.AzureContainerRegistry, name: "Azure Container Registry" },
    { identifier: ContainerRegistries.Harbor, name: "Harbor" },
];

/**
 * The fields the dialog edits. Everything else on the model - the `has_*` flags, the
 * images - is read-only, and the secrets are sent only when something was typed: an
 * empty secret keeps the stored one, see ContainerRegistry::patch().
 */
const editableFields: (keyof ContainerRegistry)[] = [
    "name",
    "provider",
    "gcloud_project",
    "gcloud_location",
    "gcloud_registry_name",
    "azure_registry_name",
    "azure_tenant",
    "azure_client_id",
    "azure_subscription_id",
    "azure_resource_group",
    "harbor_url",
    "harbor_username",
    "pull_username",
    "events_enabled",
];
const secretFields: (keyof ContainerRegistry)[] = ["gcloud_credentials", "azure_client_secret", "harbor_password", "pull_password"];

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

function render() {
    if (props.input.containerRegistry.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.containerRegistries()
            .getById(props.input.containerRegistry.id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
            });
    } else {
        item.value = props.input.containerRegistry;
        showDialog.value = true;
    }
}

function payload(): any {
    const data: any = {};
    for (const field of editableFields) {
        data[field] = item.value[field];
    }
    for (const field of secretFields) {
        if ((item.value[field] as string | undefined)?.length) {
            data[field] = item.value[field];
        }
    }
    return data;
}

function secretHint(field: "gcloud_credentials" | "azure_client_secret" | "harbor_password" | "pull_password"): string {
    return item.value[`has_${field}`] ? "Stored. Leave empty to keep it." : "Not set.";
}

function close() {
    showDialog.value = false;
    bus.emit("containerRegistryEditDialog_closed", item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value.exists()
        ? Api.containerRegistries().patchById(item.value.id!)
        : Api.containerRegistries().post();

    save(api, payload(), newItem => {
        bus.emit("containerRegistrySaved", newItem);
        close();
    });
}

function onTestBtnClicked() {
    isTesting.value = true;
    testResult.value = null;
    ApiService.apiAxios!.get(`/container-registries/${item.value.id}/test`)
        .then(response => {
            const ok = response.data?.status === "OK";
            testResult.value = {
                ok: ok,
                message: ok ? response.data.resource?.message : String(response.data?.error ?? "Failed"),
            };
        })
        .catch(error => {
            testResult.value = { ok: false, message: error?.message ?? "Failed" };
        })
        .finally(() => (isTesting.value = false));
}

/**
 * What a pull login is called at each provider. Artifact Registry takes a key file with
 * the username `_json_key`.
 */
function pullUsernameHint(): string {
    switch (item.value.provider) {
        case ContainerRegistries.ArtifactContainerRegistry:
            return "_json_key - with a service account key (JSON) as password";
        case ContainerRegistries.AzureContainerRegistry:
            return "Eg. a token name, or a service principal's client ID";
        case ContainerRegistries.Harbor:
            return "Eg. robot$kso-pull";
    }
    return "";
}

/**
 * Harbor and Azure get a webhook with a secret, made by kso; Artifact Registry a Pub/Sub
 * subscription. Either way the registry is written to, so it is its own action.
 */
function onSetupEventsBtnClicked() {
    isSettingUpEvents.value = true;
    testResult.value = null;
    ApiService.apiAxios!.post(`/container-registries/${item.value.id}/setup-events`)
        .then(response => {
            const ok = response.data?.status === "OK";
            testResult.value = { ok: ok, message: ok ? response.data.resource?.message : String(response.data?.error ?? "Failed") };
            if (ok) {
                item.value.events_enabled = true;
                item.value.has_webhook_secret = item.value.provider != ContainerRegistries.ArtifactContainerRegistry;
            }
        })
        .catch(error => (testResult.value = { ok: false, message: error?.message ?? "Failed" }))
        .finally(() => (isSettingUpEvents.value = false));
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>
</script>

<template>
    <v-dialog persistent height="70vh" width="60vw" v-model="showDialog">
        <v-card class="w-100 h-100" :loading="isLoading" :disabled="isLoading">
            <v-card-title>Container Registry</v-card-title>
            <v-divider />
            <v-card-text>
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field variant="outlined" v-model="item.name" label="Name" density="compact" />
                    </v-col>
                    <v-col cols="12">
                        <v-select
                            v-model="item.provider"
                            :items="providers"
                            item-title="name"
                            item-value="identifier"
                            variant="outlined"
                            label="Provider"
                            density="compact"
                        />
                    </v-col>

                    <template v-if="item.provider == ContainerRegistries.ArtifactContainerRegistry">
                        <v-col cols="12">
                            <div class="border pa-2 mb-2">
                                KSO uses a service account to fetch image tags and, with events enabled, to
                                subscribe to new tags. It needs these roles:
                                <ul class="ml-5">
                                    <li>Artifact Registry Reader</li>
                                    <li>Pub/Sub Editor (only with events enabled)</li>
                                </ul>
                            </div>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field variant="outlined" v-model="item.gcloud_project" label="Google Cloud Project" density="compact" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                v-model="item.gcloud_location"
                                label="Location"
                                hint="Eg. europe"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                v-model="item.gcloud_registry_name"
                                label="Repository"
                                hint="Eg. docker, or eu.gcr.io for a repository migrated from Container Registry"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-textarea
                                variant="outlined"
                                v-model="item.gcloud_credentials"
                                label="Service account key (JSON)"
                                :hint="secretHint('gcloud_credentials')"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                    </template>

                    <template v-if="item.provider == ContainerRegistries.AzureContainerRegistry">
                        <v-col cols="12">
                            <div class="border pa-2 mb-2">
                                KSO uses a service principal to fetch image tags and list repositories. To report new tags,
                                kso creates a webhook on the registry through Azure's management API, which needs the
                                subscription and resource group below. Roles on the registry:
                                <ul class="ml-5">
                                    <li>AcrPull</li>
                                    <li>
                                        For auto update: Contributor, or a custom role with
                                        Microsoft.ContainerRegistry/registries/read and
                                        Microsoft.ContainerRegistry/registries/webhooks/read + write
                                    </li>
                                </ul>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <v-text-field variant="outlined" v-model="item.azure_tenant" label="Microsoft Entra ID Tenant" density="compact" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                v-model="item.azure_registry_name"
                                label="Login server"
                                hint="Eg. acme.azurecr.io"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field variant="outlined" v-model="item.azure_client_id" label="Client ID (Application ID)" density="compact" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field variant="outlined" v-model="item.azure_subscription_id" label="Subscription ID" density="compact" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field variant="outlined" v-model="item.azure_resource_group" label="Resource group" density="compact" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                type="password"
                                v-model="item.azure_client_secret"
                                label="Client secret"
                                :hint="secretHint('azure_client_secret')"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                    </template>

                    <template v-if="item.provider == ContainerRegistries.Harbor">
                        <v-col cols="12">
                            <div class="border pa-2 mb-2">
                                KSO uses a robot account to fetch image tags and list repositories. To report new tags, kso
                                creates a webhook policy named kso on each project its images live in. Robot account
                                permissions, on each of those projects:
                                <ul class="ml-5">
                                    <li>Repository: List</li>
                                    <li>Artifact: List</li>
                                    <li>For auto update - Webhook (notification-policy): List, Create, Update</li>
                                </ul>
                            </div>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                                variant="outlined"
                                v-model="item.harbor_url"
                                label="Registry host"
                                hint="Eg. harbor.example.org - without https://"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                v-model="item.harbor_username"
                                label="Robot username"
                                hint="Eg. robot$kso"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                type="password"
                                v-model="item.harbor_password"
                                label="Robot token"
                                :hint="secretHint('harbor_password')"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                    </template>

                    <template v-if="item.provider">
                        <v-col cols="12">
                            <div class="border pa-2 mb-2">
                                Pull secret. With a login of its own that may only pull, kso creates a secret named
                                kso-registry-{{ item.id ?? "&lt;id&gt;" }} in each namespace deploying images from this
                                registry, and the pods use it. Leave empty to keep using the pull secret set on each image.
                                The login needs:
                                <ul class="ml-5">
                                    <li v-if="item.provider == ContainerRegistries.ArtifactContainerRegistry">
                                        A service account with Artifact Registry Reader
                                    </li>
                                    <li v-if="item.provider == ContainerRegistries.AzureContainerRegistry">
                                        AcrPull, or a repository-scoped token with content/read
                                    </li>
                                    <li v-if="item.provider == ContainerRegistries.Harbor">
                                        A robot account with Repository: Pull, on each project
                                    </li>
                                </ul>
                            </div>
                        </v-col>
                        <v-col cols="6">
                            <v-text-field
                                variant="outlined"
                                v-model="item.pull_username"
                                label="Pull username"
                                :hint="pullUsernameHint()"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                        <!-- Artifact Registry's password is a service account key: a whole JSON file,
                             so it gets the width and height the key above has. -->
                        <v-col :cols="item.provider == ContainerRegistries.ArtifactContainerRegistry ? 12 : 6">
                            <v-textarea
                                v-if="item.provider == ContainerRegistries.ArtifactContainerRegistry"
                                variant="outlined"
                                v-model="item.pull_password"
                                label="Pull password (service account key JSON)"
                                :hint="secretHint('pull_password')"
                                persistent-hint
                                density="compact"
                            />
                            <v-text-field
                                v-else
                                variant="outlined"
                                type="password"
                                v-model="item.pull_password"
                                label="Pull password"
                                :hint="secretHint('pull_password')"
                                persistent-hint
                                density="compact"
                            />
                        </v-col>
                    </template>

                    <v-col cols="12" v-if="item.provider == ContainerRegistries.ArtifactContainerRegistry">
                        <v-switch
                            v-model="item.events_enabled"
                            label="Receive new tags (auto update) - subscribes when saved"
                            density="compact"
                            color="secondary"
                            hide-details
                        />
                    </v-col>
                    <v-col cols="12" v-else-if="item.provider && item.exists()">
                        <v-alert v-if="item.has_webhook_secret" density="compact" variant="tonal" type="info">
                            Auto update is set up. Set it up again after importing images into a new project.
                        </v-alert>
                        <v-alert v-else density="compact" variant="tonal" type="warning">
                            Auto update is not set up. Pushes are refused until it is.
                        </v-alert>
                    </v-col>

                    <v-col cols="12" v-if="testResult">
                        <v-alert density="compact" variant="tonal" :type="testResult.ok ? 'success' : 'error'">
                            {{ testResult.message }}
                        </v-alert>
                    </v-col>
                </v-row>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-btn
                    v-if="item.exists()"
                    variant="tonal"
                    prepend-icon="fa fa-plug"
                    :loading="isTesting"
                    @click="onTestBtnClicked">
                    Test connection
                </v-btn>
                <v-btn
                    v-if="item.exists() && item.provider"
                    variant="tonal"
                    prepend-icon="fa fa-bell"
                    :loading="isSettingUpEvents"
                    @click="onSetupEventsBtnClicked">
                    Set up auto update
                </v-btn>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>
                <v-btn flat variant="tonal" prepend-icon="fa fa-check" color="success" :loading="isSaving" @click="onSaveBtnClicked"> Save </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped></style>
