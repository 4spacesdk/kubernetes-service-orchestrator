<script setup lang="ts">
import { useRoute, useRouter } from "vue-router";
import { computed, defineComponent, onMounted, reactive, ref, watch } from "vue";
import { System } from "@/core/services/Deploy/models";
import { NetworkTypes } from "@/constants";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import ApiService from "@/services/ApiService";

const isLoading = ref(false);
const value = ref<System | null>(null);
const githubOrg = ref("");
const showGithubOrgDialog = ref(false);
const route = useRoute();
const router = useRouter();
const networkTypes = ref([
    {
        name: "Nginx Ingress",
        value: NetworkTypes.NginxIngress,
    },
    {
        name: "Istio",
        value: NetworkTypes.Istio,
    },
    {
        name: "Contour",
        value: NetworkTypes.Contour,
    },
    {
        name: "Gateway Api",
        value: NetworkTypes.GatewayApi,
    },
]);
const selectedNetworkTypes = ref<string[]>([]);

onMounted(() => {
    value.value = new System(System.Instance);

    if (route.query.github_success === "1" && value.value.github_app_slug) {
        bus.emit("toast", {
            text: "GitHub App created successfully! Please install it to complete the setup.",
            color: "success",
        });

        // Redirect to installation page
        const installationUrl = `https://github.com/apps/${value.value.github_app_slug}/installations/new`;
        window.location.href = installationUrl;
        return;
    }

    if (route.query.github_install_success === "1") {
        bus.emit("toast", {
            text: "GitHub App installed successfully!",
            color: "success",
        });
        // Remove query params
        router.replace({ query: {} });
    }

    if (value.value.is_network_nginx_ingress_supported) {
        selectedNetworkTypes.value.push(NetworkTypes.NginxIngress);
    }
    if (value.value.is_network_istio_supported) {
        selectedNetworkTypes.value.push(NetworkTypes.Istio);
    }
    if (value.value.is_network_contour_supported) {
        selectedNetworkTypes.value.push(NetworkTypes.Contour);
    }
    if (value.value.is_network_gateway_api_supported) {
        selectedNetworkTypes.value.push(NetworkTypes.GatewayApi);
    }
});

function onSaveBtnClicked() {
    value.value!.is_network_nginx_ingress_supported = selectedNetworkTypes.value.includes(NetworkTypes.NginxIngress);
    value.value!.is_network_istio_supported = selectedNetworkTypes.value.includes(NetworkTypes.Istio);
    value.value!.is_network_contour_supported = selectedNetworkTypes.value.includes(NetworkTypes.Contour);
    value.value!.is_network_gateway_api_supported = selectedNetworkTypes.value.includes(NetworkTypes.GatewayApi);
    Api.systems()
        .patchById(value.value!.id!)
        .save(value.value!, (system) => {
            System.Instance = system;
            bus.emit("toast", {
                text: "Saved",
            });
        });
}

function onCreateGithubAppClicked() {
    showGithubOrgDialog.value = true;
}

function onConfirmGithubAppCreation() {
    showGithubOrgDialog.value = false;
    ApiService.apiAxios!.get("githubapp/manifest", { params: { org: githubOrg.value } }).then((response) => {
        const manifest = response.data;
        const url = githubOrg.value
            ? `https://github.com/organizations/${githubOrg.value}/settings/apps/new`
            : `https://github.com/settings/apps/new`;

        const form = document.createElement("form");
        form.method = "POST";
        form.action = url;

        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "manifest";
        input.value = JSON.stringify(manifest);
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    });
}
</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar density="compact" flat color="blue-grey lighten-5" dark>
            <v-toolbar-title>System</v-toolbar-title>
        </v-toolbar>

        <v-progress-linear v-if="isLoading" indeterminate color="accent" />

        <v-row class="pa-4" v-if="!isLoading && value">
            <v-col cols="6">
                <v-select
                    v-model="selectedNetworkTypes"
                    :items="networkTypes"
                    item-title="name"
                    item-value="value"
                    label="Supported Network Types"
                    variant="outlined"
                    multiple
                    density="compact"
                    hide-details
                />
            </v-col>
            <v-col cols="12">
                <v-btn @click="onSaveBtnClicked">Save</v-btn>
            </v-col>

            <v-col cols="12">
                <v-divider class="my-4" />
                <h3 class="text-h6 mb-2">GitHub App Integration</h3>
                <div v-if="value.github_app_id">
                    <v-alert type="success" variant="tonal" density="compact">
                        GitHub App is configured (ID: {{ value.github_app_id }}, Slug: {{ value.github_app_slug }}, Installation ID:
                        {{ value.github_app_installation_id }})
                    </v-alert>
                    <v-btn color="warning" class="mt-4" @click="onCreateGithubAppClicked">Re-configure GitHub App</v-btn>
                </div>
                <div v-else>
                    <v-alert type="info" variant="tonal" density="compact">
                        GitHub App is not configured. Creating a GitHub App will allow KSO to interact with your repositories securely.
                    </v-alert>
                    <v-btn color="primary" class="mt-4" @click="onCreateGithubAppClicked">Create GitHub App</v-btn>
                </div>

                <v-dialog v-model="showGithubOrgDialog" max-width="500px" eager>
                    <v-card>
                        <v-card-title>GitHub App Configuration</v-card-title>
                        <v-card-text>
                            <p class="mb-4">
                                If you want to create the app under a specific organization, please enter the organization name below.
                                Otherwise, leave it empty to create it under your personal account.
                            </p>
                            <v-text-field
                                v-model="githubOrg"
                                label="GitHub Organization (optional)"
                                placeholder="e.g. 4spacesdk"
                                variant="outlined"
                                density="compact"
                                hide-details
                                @keyup.enter="onConfirmGithubAppCreation"
                            />
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer />
                            <v-btn variant="text" @click="showGithubOrgDialog = false">Cancel</v-btn>
                            <v-btn color="primary" @click="onConfirmGithubAppCreation">Continue to GitHub</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </v-col>
        </v-row>
    </div>
</template>

<style scoped></style>
