<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import { GithubIntegration } from "@/core/services/Deploy/models";
import GithubIntegrationList from "@/components/Modules/Integrations/GithubIntegrations/List/GithubIntegrationList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    // GitHub sends the operator back here after installing the App.
    if (route.query.github_install_success === "1") {
        bus.emit("toast", {
            text: "GitHub App installed",
            color: "success",
        });
        router.replace({ query: {} });
    }

    switch (route.name) {
        case "GithubIntegrations":
            break;
        case "GithubIntegrationById":
            Api.githubIntegrations()
                .get()
                .where("id", route.params.id)
                .find(items => {
                    if (items.length == 1) {
                        bus.emit("githubIntegrationEdit", {
                            githubIntegration: items[0],
                        });
                    }
                });
            break;
    }

    bus.on("githubIntegrationEditDialog_closed", onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off("githubIntegrationEditDialog_closed", onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({ name: "GithubIntegrations" });
}

function onItemEditClicked(item: GithubIntegration) {
    router.push({
        name: "GithubIntegrationById",
        params: {
            id: item.id,
        },
    });
}
</script>

<template>
    <GithubIntegrationList @on-item-edit-clicked="onItemEditClicked" />
</template>

<style scoped></style>
