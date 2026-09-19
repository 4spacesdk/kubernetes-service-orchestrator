<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { GithubIntegration } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

const emit = defineEmits<{
    (e: "onItemEditClicked", item: GithubIntegration): void;
}>();

const itemCount = ref(0);
const rows = ref<GithubIntegration[]>([]);
const headers = ref([
    { title: "Name", key: "name", sortable: false },
    { title: "Organisation", key: "organization", sortable: false },
    { title: "App", key: "slug", sortable: false },
    { title: "Installed", key: "installation_id", sortable: false },
    { title: "", key: "actions", sortable: false },
]);
const isLoading = ref(true);
const options = ref({});

onMounted(() => {
    bus.on("githubIntegrationSaved", onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off("githubIntegrationSaved", onItemSaved);
});

function onItemSaved() {
    getItems(true, true);
}

function getItems(doItems = true, doCount = false) {
    const tableOptions: any = options.value;

    isLoading.value = true;

    const api = Api.githubIntegrations().get();

    if (doItems) {
        api.limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderAsc("name")
            .find(items => {
                rows.value = items;
                isLoading.value = false;
            });
    }

    if (doCount) {
        api.count(count => {
            itemCount.value = count;
        });
    }
}

// <editor-fold desc="View functions">

function onCreateItemBtnClicked() {
    bus.emit("githubIntegrationEdit", {
        githubIntegration: new GithubIntegration(),
    });
}

function onEditItemBtnClicked(item: GithubIntegration) {
    bus.emit("githubIntegrationEdit", {
        githubIntegration: item,
    });
    emit("onItemEditClicked", item);
}

function onDeleteItemBtnClicked(item: GithubIntegration) {
    bus.emit("integrationDelete", {
        name: item.name!,
        imageField: "github_integration_id",
        id: item.id!,
        imageDetail: image => image.version_control_repository_name,
        note: "The App stays on GitHub; uninstall it there.",
        delete: done =>
            Api.githubIntegrations()
                .deleteById(item.id!)
                .delete(() => {
                    bus.emit("githubIntegrationSaved");
                    done();
                }),
    });
}

// </editor-fold>
</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar density="compact" flat color="blue-grey lighten-5" dark>
            <v-toolbar-title>GitHub Integrations</v-toolbar-title>

            <v-spacer></v-spacer>
            <v-btn small class="" @click="onCreateItemBtnClicked()" prepend-icon="fa fa-plus"> Create </v-btn>
        </v-toolbar>

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :loading="isLoading"
            :items-per-page="50"
            class="table"
            density="compact"
            @update:options="
                options = $event;
                getItems();
            ">
            <template v-slot:item.installation_id="{ item }">
                <v-icon v-if="item.installation_id" size="small">fa fa-check</v-icon>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">
                    <v-btn variant="plain" color="primary" size="small" @click="onEditItemBtnClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="red" size="small" @click="onDeleteItemBtnClicked(item)">
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>
    </div>
</template>

<style scoped>
.table > *,
.table {
    background: transparent;
}
</style>
