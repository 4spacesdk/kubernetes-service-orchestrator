<script setup lang="ts">
import { onMounted, ref } from "vue";
import { GithubIntegration } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import ApiService from "@/services/ApiService";

export interface GithubIntegrationEditDialog_Input {
    githubIntegration: GithubIntegration;
}

const props = defineProps<{ input: GithubIntegrationEditDialog_Input; events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);
const isRedirecting = ref(false);
const error = ref<string | null>(null);

const item = ref<GithubIntegration>(new GithubIntegration());

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

function render() {
    if (props.input.githubIntegration.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.githubIntegrations()
            .getById(props.input.githubIntegration.id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
            });
    } else {
        item.value = props.input.githubIntegration;
        showDialog.value = true;
    }
}

/**
 * Only the name and the organisation are the operator's; the rest comes from GitHub.
 */
function save(next: (saved: GithubIntegration) => void) {
    const api = item.value.exists()
        ? Api.githubIntegrations().patchById(item.value.id!)
        : Api.githubIntegrations().post();

    const data: any = { name: item.value.name, organization: item.value.organization ?? "" };
    api.save(data, saved => {
        item.value.id = saved.id;
        bus.emit("githubIntegrationSaved", saved);
        next(saved);
    });
}

/**
 * The next step of the setup happens at GitHub. kso answers with where to go, carrying a
 * state that GitHub hands back and kso checks.
 */
function setupStep(action: "create-app" | "install", next: (resource: any) => void) {
    error.value = null;
    isRedirecting.value = true;
    save(saved => {
        ApiService.apiAxios!.post(`/github-integrations/${saved.id}/${action}`)
            .then(response => {
                if (response.data?.status !== "OK") {
                    error.value = String(response.data?.error ?? "Failed");
                    isRedirecting.value = false;
                    return;
                }
                next(response.data.resource);
            })
            .catch(e => {
                error.value = e?.message ?? "Failed";
                isRedirecting.value = false;
            });
    });
}

function close() {
    showDialog.value = false;
    bus.emit("githubIntegrationEditDialog_closed", item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    save(() => close());
}

/**
 * GitHub creates an App from a manifest posted by the browser, so the operator can
 * confirm it there.
 */
function onCreateAppBtnClicked() {
    setupStep("create-app", resource => {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = resource.url;

        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "manifest";
        input.value = resource.manifest;
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    });
}

function onInstallBtnClicked() {
    setupStep("install", resource => {
        window.location.href = resource.url;
    });
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>
</script>

<template>
    <v-dialog persistent width="60vw" min-width="720" v-model="showDialog">
        <v-card class="w-100" :loading="isLoading" :disabled="isLoading || isRedirecting">
            <v-card-title>GitHub Integration</v-card-title>
            <v-divider />
            <v-card-text>
                <v-row dense>
                    <v-col cols="12">
                        <div class="border pa-2 mb-2">
                            KSO reads commit messages through a private GitHub App, one per organisation. The App asks
                            for read access to contents and metadata only. Creating it takes you to GitHub, and from
                            there straight on to installing it.
                        </div>
                    </v-col>
                    <v-col cols="12">
                        <v-text-field variant="outlined" v-model="item.name" label="Name" density="compact" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.organization"
                            label="GitHub organisation"
                            placeholder="e.g. 4spacesdk"
                            hint="Leave empty to create the App on your personal account"
                            persistent-hint
                            :disabled="item.has_private_key"
                            density="compact"
                        />
                    </v-col>

                    <v-col cols="12" v-if="item.exists()">
                        <v-alert v-if="item.installation_id" density="compact" variant="tonal" type="success">
                            App {{ item.slug }} (ID {{ item.app_id }}) is installed (installation {{ item.installation_id }}).
                        </v-alert>
                        <v-alert v-else-if="item.has_private_key" density="compact" variant="tonal" type="warning">
                            App {{ item.slug }} is created but not installed.
                        </v-alert>
                        <v-alert v-else density="compact" variant="tonal" type="info">
                            No GitHub App yet.
                        </v-alert>
                    </v-col>

                    <v-col cols="12" v-if="error">
                        <v-alert density="compact" variant="tonal" type="error">{{ error }}</v-alert>
                    </v-col>
                </v-row>
            </v-card-text>
            <v-divider />
            <v-card-actions class="flex-wrap">
                <v-btn
                    variant="tonal"
                    prepend-icon="fa fa-code-branch"
                    :color="item.has_private_key ? 'warning' : 'primary'"
                    :loading="isRedirecting"
                    @click="onCreateAppBtnClicked">
                    {{ item.has_private_key ? "Create App again" : "Create GitHub App" }}
                </v-btn>
                <v-btn
                    v-if="item.has_private_key"
                    variant="tonal"
                    prepend-icon="fa fa-download"
                    :loading="isRedirecting"
                    @click="onInstallBtnClicked">
                    {{ item.installation_id ? "Change repositories" : "Install" }}
                </v-btn>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>
                <v-btn flat variant="tonal" prepend-icon="fa fa-check" color="success" @click="onSaveBtnClicked"> Save </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped></style>
