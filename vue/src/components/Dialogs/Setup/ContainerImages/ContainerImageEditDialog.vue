<script setup lang="ts">
import { computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { ContainerImage, ContainerRegistry, GithubIntegration } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import { CommitIdentificationMethods, ImagePullPolicies, VersionControlProviders } from "@/constants";
import ApiService from "@/services/ApiService";

export interface ContainerImageEditDialog_Input {
    containerImage: ContainerImage;
}

const props = defineProps<{
    input: ContainerImageEditDialog_Input;
    events: DialogEventsInterface;
}>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const tab = ref("basic");
const item = ref<ContainerImage>(new ContainerImage());
const showPullSecret = ref(false);

const githubRepositories = ref<{ id: number; full_name: string; name: string }[]>([]);
const isLoadingRepositories = ref(false);
const githubRepositoriesError = ref<string | null>(null);

const githubIntegrations = ref<GithubIntegration[]>([]);

const containerRegistries = ref<ContainerRegistry[]>([]);
const isLoadingRegistries = ref(false);

const commitIdentificationMethods = ref([
    {
        identifier: CommitIdentificationMethods.EnvironmentVariable,
        name: "Environment Variable",
    },
]);

const versionControlProviders = ref([
    {
        identifier: VersionControlProviders.GitHub,
        name: "GitHub",
    },
]);
const imagePullPolicies = ref([
    {
        identifier: ImagePullPolicies.IfNotPresent,
        name: "If not present",
    },
    {
        identifier: ImagePullPolicies.Always,
        name: "Always",
    },
    {
        identifier: ImagePullPolicies.Never,
        name: "Never",
    },
]);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    load();
    loadRegistries();
    loadGithubIntegrations();
});

function loadGithubIntegrations() {
    Api.githubIntegrations()
        .get()
        .orderAsc("name")
        .find(items => {
            githubIntegrations.value = items;
            pickTheOnlyGithubIntegration();
        });
}

/**
 * With one organisation connected there is nothing to choose.
 */
function pickTheOnlyGithubIntegration() {
    if (item.value.version_control_provider == VersionControlProviders.GitHub
        && !item.value.github_integration_id
        && githubIntegrations.value.length == 1) {
        item.value.github_integration_id = githubIntegrations.value[0].id;
    }
}

function loadRegistries() {
    isLoadingRegistries.value = true;
    Api.containerRegistries()
        .get()
        .orderAsc("name")
        .find(items => {
            containerRegistries.value = items;
            isLoadingRegistries.value = false;
        });
}

function onCreateRegistryBtnClicked() {
    bus.emit("containerRegistryEdit", { containerRegistry: new ContainerRegistry() });
}

function onRegistrySaved(registry: ContainerRegistry | undefined) {
    loadRegistries();
    if (registry?.id && !item.value.container_registry_id) {
        item.value.container_registry_id = registry.id;
    }
}

bus.on("containerRegistrySaved", onRegistrySaved);
onUnmounted(() => bus.off("containerRegistrySaved", onRegistrySaved));

onUnmounted(() => {});

function load() {
    if (props.input.containerImage.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.containerImages()
            .getById(props.input.containerImage.id!)
            .find((items) => {
                item.value = items[0];
                isLoading.value = false;
                render();
            });
    } else {
        item.value = props.input.containerImage;
        showDialog.value = true;
        render();
    }
}

function render() {
    showPullSecret.value = (item.value.pull_secret?.length ?? 0) > 0;
}

function fetchGithubRepositories() {
    githubRepositories.value = [];
    githubRepositoriesError.value = null;
    const integrationId = item.value.github_integration_id;
    if (item.value.version_control_provider != VersionControlProviders.GitHub || !integrationId) {
        return;
    }

    isLoadingRepositories.value = true;
    ApiService.apiAxios!.get(`/github-integrations/${integrationId}/repositories`)
        .then((response) => {
            if (response.data?.status === "OK") {
                githubRepositories.value = response.data.resources;
            } else {
                githubRepositoriesError.value = String(response.data?.error ?? "Failed");
            }
        })
        .finally(() => {
            isLoadingRepositories.value = false;
        });
}

watch(
    () => [item.value.version_control_provider, item.value.github_integration_id],
    () => {
        pickTheOnlyGithubIntegration();
        fetchGithubRepositories();
    }
);

function close() {
    showDialog.value = false;
    bus.emit("containerImageEditDialog_closed", item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    if (!showPullSecret.value) {
        item.value.pull_secret = "";
    }
    // The connection is chosen by id. The loaded relation is left out, so the save cannot
    // be read as a request to change the connection itself.
    item.value.container_registry = undefined;
    item.value.container_registry_id = item.value.container_registry_id ?? 0;
    item.value.github_integration = undefined;
    item.value.github_integration_id = item.value.github_integration_id ?? 0;
    const api = item.value!.exists() ? Api.containerImages().patchById(item.value!.id!) : Api.containerImages().post();

    api.save(item.value!, (newItem) => {
        bus.emit("containerImageSaved", newItem);
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
    <v-dialog persistent height="60vh" width="60vw" v-model="showDialog">
        <v-card class="w-100 h-100" :loading="isLoading" :disabled="isLoading">
            <v-card-title class="d-flex">
                <span class="my-auto">Container Image</span>
                <v-tabs v-model="tab" density="compact" class="ml-auto">
                    <v-tab value="basic">Info</v-tab>
                    <v-tab value="security">Security</v-tab>
                    <v-tab value="registry">Registry</v-tab>
                    <v-tab value="version-control">VCS</v-tab>
                </v-tabs>
            </v-card-title>
            <v-divider />
            <v-card-text>
                <v-tabs-window v-model="tab" class="pt-1">
                    <v-tabs-window-item value="basic">
                        <v-row dense class="pb-4 px-2 pt-2">
                            <v-col cols="12">
                                <v-text-field variant="outlined" v-model="item.name" label="Name" />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field variant="outlined" v-model="item.url" label="Url" density="compact" />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    variant="outlined"
                                    v-model="item.default_tag"
                                    label="Default Tag"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>

                            <v-col cols="6">
                                <v-select
                                    v-model="item.default_image_pull_policy"
                                    :items="imagePullPolicies"
                                    item-title="name"
                                    item-value="identifier"
                                    variant="outlined"
                                    label="Default Image Pull Policy"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="12" class="mt-4">
                                <v-card class="px-2 mx-1">
                                    <v-switch
                                        v-model="showPullSecret"
                                        variant="outlined"
                                        label="Use image pull secret"
                                        density="compact"
                                        color="secondary"
                                    />
                                    <div v-if="showPullSecret">
                                        <v-row>
                                            <v-col cols="12">
                                                <v-text-field
                                                    variant="outlined"
                                                    v-model="item.pull_secret"
                                                    label="Image pull secret"
                                                    density="compact"
                                                    hide-details
                                                />
                                            </v-col>
                                        </v-row>
                                    </div>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-tabs-window-item>

                    <v-tabs-window-item value="security">
                        <v-row dense class="pb-4 px-2 pt-2">
                            <v-col cols="4">
                                <v-text-field
                                    variant="outlined"
                                    type="number"
                                    v-model.number="item.security_context_run_as_user"
                                    label="Run as user"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="4">
                                <v-text-field
                                    variant="outlined"
                                    type="number"
                                    v-model.number="item.security_context_run_as_group"
                                    label="Run as group"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="4">
                                <v-text-field
                                    variant="outlined"
                                    type="number"
                                    v-model.number="item.security_context_fs_group"
                                    label="FS Group"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-switch
                                    v-model="item.security_context_allow_privilege_escalation"
                                    variant="outlined"
                                    label="Allow privilege escalation"
                                    density="compact"
                                    color="secondary"
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-switch
                                    v-model="item.security_context_read_only_root_filesystem"
                                    variant="outlined"
                                    label="Readonly root filesystem"
                                    density="compact"
                                    color="secondary"
                                />
                            </v-col>
                        </v-row>
                    </v-tabs-window-item>

                    <v-tabs-window-item value="registry">
                        <v-row dense class="pb-4 px-2">
                            <v-col cols="12">
                                <v-select
                                    v-model="item.container_registry_id"
                                    :items="containerRegistries"
                                    :loading="isLoadingRegistries"
                                    item-title="name"
                                    item-value="id"
                                    variant="outlined"
                                    label="Registry connection"
                                    hint="Where kso asks for tags and receives new ones. None for an image from a public registry."
                                    persistent-hint
                                    clearable
                                    density="compact">
                                    <template v-slot:append>
                                        <v-btn variant="tonal" height="40" prepend-icon="fa fa-plus" @click="onCreateRegistryBtnClicked">
                                            New
                                        </v-btn>
                                    </template>
                                </v-select>
                            </v-col>
                        </v-row>
                    </v-tabs-window-item>

                    <v-tabs-window-item value="version-control">
                        <v-row dense class="pb-4 px-2">
                            <v-col cols="12">
                                <v-card class="px-2 mb-4">
                                    <v-switch
                                        v-model="item.version_control_enabled"
                                        variant="outlined"
                                        label="Setup version control"
                                        density="compact"
                                        color="secondary"
                                    />
                                    <div v-if="item.version_control_enabled">
                                        <v-row>
                                            <v-col cols="12">
                                                <v-select
                                                    v-model="item.version_control_provider"
                                                    :items="versionControlProviders"
                                                    item-title="name"
                                                    item-value="identifier"
                                                    variant="outlined"
                                                    label="Version Control System"
                                                    density="compact"
                                                    hide-details
                                                />
                                            </v-col>

                                            <v-col cols="12" v-if="item.version_control_provider == VersionControlProviders.GitHub">
                                                <v-select
                                                    v-model="item.github_integration_id"
                                                    :items="githubIntegrations"
                                                    item-title="name"
                                                    item-value="id"
                                                    variant="outlined"
                                                    label="GitHub integration"
                                                    :hint="githubIntegrations.length ? '' : 'None yet - create one under Integrations, GitHub Integrations'"
                                                    persistent-hint
                                                    density="compact"
                                                />
                                            </v-col>

                                            <v-col cols="12">
                                                <v-combobox
                                                    v-if="item.version_control_provider == VersionControlProviders.GitHub"
                                                    class="repository-combobox"
                                                    variant="outlined"
                                                    v-model="item.version_control_repository_name"
                                                    :items="githubRepositories"
                                                    item-title="name"
                                                    item-value="full_name"
                                                    :return-object="false"
                                                    label="Repository name"
                                                    density="compact"
                                                    :loading="isLoadingRepositories"
                                                    :error-messages="githubRepositoriesError ?? undefined"
                                                />
                                                <v-text-field
                                                    v-else
                                                    variant="outlined"
                                                    v-model="item.version_control_repository_name"
                                                    label="Repository name (owner/repo)"
                                                    density="compact"
                                                    hide-details
                                                />
                                            </v-col>
                                        </v-row>
                                    </div>
                                </v-card>
                            </v-col>

                            <v-col cols="12">
                                <v-card class="px-2">
                                    <v-switch
                                        v-model="item.commit_identification_enabled"
                                        variant="outlined"
                                        label="Setup commit identification"
                                        density="compact"
                                        color="secondary"
                                    />
                                    <div v-if="item.commit_identification_enabled">
                                        <v-row>
                                            <v-col cols="12">
                                                <v-select
                                                    v-model="item.commit_identification_method"
                                                    :items="commitIdentificationMethods"
                                                    item-title="name"
                                                    item-value="identifier"
                                                    variant="outlined"
                                                    label="Commit Identification Method"
                                                    density="compact"
                                                    hide-details
                                                />
                                            </v-col>

                                            <v-col
                                                cols="12"
                                                v-if="item.commit_identification_method == CommitIdentificationMethods.EnvironmentVariable"
                                            >
                                                <div class="border pa-2">
                                                    KSO can fetch git commit sha from an environment variable inside deployed container.
                                                    <br />
                                                    You must specify the name of the environment variable.
                                                    <br />
                                                    KSO can then fetch commit message from version control and use the commit message to
                                                    perform post update actions.
                                                    <br />
                                                    Typical flow
                                                    <ul class="ml-5">
                                                        <li>1) Developer provide task/issue url in the commit message</li>
                                                        <li>2) Version control triggers build</li>
                                                        <li>
                                                            3) Build pipeline injects git commit sha as environment variable in the docker
                                                            container
                                                        </li>
                                                        <li>4) Build pipeline pushes container to registry</li>
                                                        <li>5) Registry triggers KSO auto update (optional approval step)</li>
                                                        <li>6) After rollout KSO fetches git commit sha from running container</li>
                                                        <li>7) KSO interacts with version control system to fetch commit message</li>
                                                        <li>
                                                            8) KSO interacts with project management system to identify related task/issue.
                                                            Based on reference found in commit message.
                                                        </li>
                                                        <li>9) KSO can update task/issue based on predefined actions. Could be:</li>
                                                        <li class="ml-4">Move status from "development" to "test"</li>
                                                        <li class="ml-4">Attach link to version control commit</li>
                                                    </ul>
                                                </div>
                                            </v-col>
                                            <v-col
                                                cols="12"
                                                v-if="item.commit_identification_method == CommitIdentificationMethods.EnvironmentVariable"
                                            >
                                                <v-text-field
                                                    variant="outlined"
                                                    v-model="item.commit_identification_environment_variable_name"
                                                    label="Environment Variable Name"
                                                    density="compact"
                                                />
                                            </v-col>
                                        </v-row>
                                    </div>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-tabs-window-item>
                </v-tabs-window>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>

                <v-btn flat variant="tonal" prepend-icon="fa fa-check" color="green" @click="onSaveBtnClicked"> Save </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
:deep(.repository-combobox .v-field__input) {
    min-height: 40px !important;
}
</style>
