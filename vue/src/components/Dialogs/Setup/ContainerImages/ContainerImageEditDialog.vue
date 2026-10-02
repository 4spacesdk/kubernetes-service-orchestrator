<script setup lang="ts">
import { ReferenceData } from "@/core/referenceData";
import { useDialogSave } from "@/composables/useDialogSave";
import { computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { ContainerImage, ContainerRegistry, GithubIntegration } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import { CommitIdentificationMethods, ImagePullPolicies, VersionControlProviders } from "@/constants";
import ApiService from "@/services/ApiService";
import moment from "moment";

export interface ContainerImageEditDialog_Input {
    containerImage: ContainerImage;
}

const props = defineProps<{
    input: ContainerImageEditDialog_Input;
    events: DialogEventsInterface;
}>();

const { form, isSaving, save } = useDialogSave();

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
    ReferenceData.githubIntegrations().then(items => {
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
    ReferenceData.containerRegistries().then(items => {
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
        // Secure from the start: it seldom stops an image. Run as non-root is stamped from what
        // the image runs as, read from its registry once it is saved.
        item.value.security_context_seccomp_runtime_default ??= true;
        showDialog.value = true;
        render();
    }
}

/**
 * Where the image writes, as chips - stored comma-separated, see `WritablePaths`. Absolute paths only.
 */
const writablePaths = computed<string[]>({
    get: () => (item.value.writable_paths ?? '').split(',').map(path => path.trim()).filter(Boolean),
    set: paths => item.value.writable_paths = [...new Set(paths.map(path => path.trim()).filter(Boolean))].join(','),
});
const writablePathsError = computed(() => {
    const wrong = writablePaths.value.find(path => !path.startsWith('/') || path.includes('..') || /[\s:]/.test(path));
    return wrong ? `${wrong} is not an absolute path, such as /tmp` : '';
});

/**
 * The tag to read what the image runs as from - the one read last, else the default. The
 * registry's tags are offered, newest first with when each was pushed; any can be typed.
 */
const userTag = ref<string | null>(null);
const registryTags = ref<{ name: string; pushed?: string }[]>([]);
const isLoadingRegistryTags = ref(false);
const isReadingUser = ref(false);

/** Asked for when the Security tab is first opened. A registry that refuses leaves the field to typing. */
function loadRegistryTags() {
    if (!item.value.exists() || !item.value.container_registry_id || registryTags.value.length || isLoadingRegistryTags.value) {
        return;
    }
    isLoadingRegistryTags.value = true;
    const api = Api.containerImages().getTagsGetById(item.value.id!);
    api.setErrorHandler(() => {
        isLoadingRegistryTags.value = false;
        return false;
    });
    api.find((responses) => {
        registryTags.value = [...(responses[0]?.tags ?? [])]
            .filter((tag) => tag.name)
            .sort((a, b) => (b.pushed_at ?? "").localeCompare(a.pushed_at ?? ""))
            .map((tag) => ({
                name: tag.name!,
                pushed: tag.pushed_at ? moment(tag.pushed_at).format("D/M-YY HH:mm") : undefined,
            }));
        isLoadingRegistryTags.value = false;
    });
}

watch(tab, (value) => {
    if (value === "security") {
        userTag.value ||= item.value.image_user_tag || item.value.default_tag || null;
        loadRegistryTags();
    }
});

/**
 * Read what a tag runs as from the registry, and stamp Run as non-root from it - for an image
 * that changed its user, or one made before kso read images.
 */
function onReadUserBtnClicked() {
    isReadingUser.value = true;
    const api = Api.containerImages().readUserPutById(item.value.id!);
    if (userTag.value?.trim()) {
        api.tag(userTag.value.trim());
    }
    api.setErrorHandler((response: any) => {
        bus.emit("toast", {text: response?.error ?? "Could not be read"});
        isReadingUser.value = false;
        return false;
    });
    api.save(null, (read: ContainerImage) => {
        item.value.image_user = read.image_user;
        item.value.image_user_tag = read.image_user_tag;
        item.value.image_user_read_at = read.image_user_read_at;
        item.value.image_user_error = read.image_user_error;
        item.value.security_context_run_as_non_root = read.security_context_run_as_non_root;
        item.value.security_advice = undefined;
        isReadingUser.value = false;
    });
}

/** What the registry said, in words. */
const runsAs = computed(() => {
    if (item.value.image_user_error) {
        return `Could not be read: ${item.value.image_user_error}`;
    }
    if (!item.value.image_user_tag) {
        return item.value.exists() ? "Not read yet" : "Read from the registry when the image is saved";
    }
    const user = item.value.image_user ? item.value.image_user : "root - it sets no user";
    return `${item.value.image_user_tag} runs as ${user}`;
});

const advice = computed<{ level: string; text: string }[]>(() => {
    try {
        return JSON.parse(item.value.security_advice ?? "[]");
    } catch {
        return [];
    }
});

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

    const isNew = !item.value.exists();
    save(api, item.value!, (newItem) => {
        if (!isNew) {
            bus.emit("containerImageSaved", newItem);
            close();
            return;
        }
        // Made secure as it is made: what it runs as, read from its registry. An image the
        // registry will not show is saved all the same, and says why on its Security tab.
        const read = Api.containerImages().readUserPutById(newItem.id!);
        read.setErrorHandler(() => {
            bus.emit("containerImageSaved", newItem);
            close();
            return false;
        });
        read.save(null, (stamped: ContainerImage) => {
            bus.emit("containerImageSaved", stamped);
            close();
        });
    });
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
            <!-- No side padding here: the tabs slide, and inside a padded box they are cut off at
                 its edge while they do. Each tab pads itself. -->
            <v-card-text class="px-0">
                <v-form ref="form" @submit.prevent>
                    <v-tabs-window v-model="tab" class="pt-1">
                        <v-tabs-window-item value="basic">
                            <v-row density="compact" class="pb-4 px-4 pt-2">
                                <v-col cols="12">
                                    <v-text-field variant="outlined" v-model="item.name" label="Name" density="compact" :rules="[v => !!(v ?? '').toString().trim() || 'Required']" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field variant="outlined" v-model="item.url" label="Url" density="compact" :rules="[v => !!(v ?? '').toString().trim() || 'Required']" />
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
                                    <v-card class="pa-4">
                                        <v-switch
                                            v-model="showPullSecret"
                                            variant="outlined"
                                            label="Use image pull secret"
                                            density="compact"
                                            color="secondary"
                                            hide-details
                                        />
                                        <div class="mt-4" v-if="showPullSecret">
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
                            <v-row density="compact" class="pb-4 px-4 pt-2">
                                <v-col cols="12">
                                    <div class="d-flex align-center flex-wrap ga-2">
                                        <v-icon size="small" class="text-medium-emphasis">fa fa-user</v-icon>
                                        <span :class="{'text-error': !!item.image_user_error}">{{ runsAs }}</span>
                                        <template v-if="item.exists()">
                                            <v-spacer />
                                            <v-combobox
                                                v-model="userTag"
                                                :items="registryTags"
                                                item-title="name"
                                                item-value="name"
                                                :return-object="false"
                                                :loading="isLoadingRegistryTags"
                                                placeholder="Tag"
                                                variant="outlined"
                                                density="compact"
                                                hide-details
                                                max-width="220"
                                                @keydown.enter.stop="onReadUserBtnClicked">
                                                <template v-slot:item="{ props: itemProps, internalItem: tagItem }">
                                                    <v-list-item v-bind="itemProps">
                                                        <template v-slot:append>
                                                            <span
                                                                v-if="tagItem.raw.pushed"
                                                                class="text-body-small text-medium-emphasis ml-4">{{ tagItem.raw.pushed }}</span>
                                                        </template>
                                                    </v-list-item>
                                                </template>
                                            </v-combobox>
                                            <v-btn
                                                size="small"
                                                variant="tonal"
                                                prepend-icon="fa fa-magnifying-glass"
                                                :loading="isReadingUser"
                                                @click="onReadUserBtnClicked">
                                                Read again
                                            </v-btn>
                                        </template>
                                    </div>
                                    <div class="text-body-small text-medium-emphasis mt-1">
                                        Read from the registry, and Run as non-root set from it: on when the image runs as a number that is not root
                                    </div>
                                </v-col>
                                <v-col v-if="advice.length" cols="12">
                                    <v-alert
                                        v-for="(line, index) in advice"
                                        :key="index"
                                        :type="line.level == 'warning' ? 'warning' : 'info'"
                                        variant="tonal"
                                        density="compact"
                                        class="mb-1">
                                        {{ line.text }}
                                    </v-alert>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-switch
                                        v-model="item.security_context_run_as_non_root"
                                        label="Run as non-root"
                                        density="compact"
                                        color="secondary"
                                        persistent-hint
                                        hint="Kubernetes will not start a container that would run as root"
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-switch
                                        v-model="item.security_context_seccomp_runtime_default"
                                        label="Seccomp profile RuntimeDefault"
                                        density="compact"
                                        color="secondary"
                                        persistent-hint
                                        hint="The runtime's default filter of system calls"
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-switch
                                        v-model="item.security_context_drop_all_capabilities"
                                        label="Drop all capabilities"
                                        density="compact"
                                        color="secondary"
                                        persistent-hint
                                        hint="An image that needs one - a port below 1024 as non-root - will fail"
                                    />
                                </v-col>
                                <v-col cols="4">
                                    <v-text-field
                                        variant="outlined"
                                        type="number"
                                        v-model.number="item.security_context_run_as_user"
                                        label="Run as user"
                                        density="compact"
                                        persistent-hint
                                        hint="For an image whose USER is a name. Empty lets the image decide"
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
                                <v-col cols="12">
                                    <v-combobox
                                        v-model="writablePaths"
                                        multiple
                                        chips
                                        closable-chips
                                        variant="outlined"
                                        density="compact"
                                        label="Writable paths"
                                        placeholder="/tmp"
                                        :error-messages="writablePathsError ? [writablePathsError] : []"
                                        hint="Where it writes - each an empty directory when the root filesystem is read-only. Read from its label dk.4spaces.kso.writable-paths when it is read"
                                        persistent-hint
                                    />
                                </v-col>
                            </v-row>
                        </v-tabs-window-item>

                        <v-tabs-window-item value="registry">
                            <v-row density="compact" class="pb-4 px-4 pt-2">
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
                            <v-row density="compact" class="pb-4 px-4 pt-2">
                                <v-col cols="12">
                                    <v-card class="pa-4 mb-4">
                                        <v-switch
                                            v-model="item.version_control_enabled"
                                            variant="outlined"
                                            label="Setup version control"
                                            density="compact"
                                            color="secondary"
                                            hide-details
                                        />
                                        <div class="mt-4" v-if="item.version_control_enabled">
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
                                    <v-card class="pa-4">
                                        <v-switch
                                            v-model="item.commit_identification_enabled"
                                            variant="outlined"
                                            label="Setup commit identification"
                                            density="compact"
                                            color="secondary"
                                            hide-details
                                        />
                                        <div class="mt-4" v-if="item.commit_identification_enabled">
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
                </v-form>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>

                <v-btn flat variant="tonal" prepend-icon="fa fa-check" color="success" :loading="isSaving" @click="onSaveBtnClicked"> Save </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
:deep(.repository-combobox .v-field__input) {
    min-height: 40px !important;
}
</style>
