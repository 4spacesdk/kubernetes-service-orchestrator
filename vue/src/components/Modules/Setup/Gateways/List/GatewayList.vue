<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import { Gateway } from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: "onItemEditClicked", item: Gateway): void;
}>();

interface Row {
    gateway: Gateway;
    status: string;
    address?: string;
}

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref<
    { title: string; key: string; sortable: boolean; align?: string }[]
>([]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref("");

onMounted(() => {
    bus.on("gatewaySaved", onItemSaved);

    getItems(false, true);

    headers.value = [
        { title: "Status", key: "status", sortable: false, align: "center" },
        { title: "Name", key: "gateway.name", sortable: false },
        { title: "Address", key: "address", sortable: false },
        { title: "Class", key: "gateway.gateway_class_name", sortable: false },
        { title: "Namespace", key: "gateway.namespace", sortable: false },
        { title: "Domains", key: "domains", sortable: false },
        { title: "", key: "actions", sortable: false },
    ];
});

onUnmounted(() => {
    bus.off("gatewaySaved", onItemSaved);
});

watch(
    searchValue,
    debounce(() => {
        getItems(true, true);
    }, 500)
);

function onItemSaved() {
    getItems(true, true);
}

function getItems(doItems = true, doCount = false) {
    const tableOptions: any = options.value;
    isLoading.value = true;
    const api = Api.gateways().get().include("domain");

    if (searchValue.value?.length) {
        api.search("name", searchValue.value);
    }

    if (doItems) {
        api.limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderAsc("name")
            .find((items) => {
                rows.value = items.map((item) => ({
                    gateway: item,
                    status: "loading",
                }));
                isLoading.value = false;

                items.forEach((item, index) => {
                    updateRowStatus(rows.value[index]);
                });
            });
    }

    if (doCount) {
        api.count((count) => {
            itemCount.value = count;
        });
    }
}

function updateRowStatus(row: Row) {
    row.status = "loading";
    Api.gateways()
        .getStatusGetById(row.gateway.id!)
        .find((res) => {
            row.status = res[0].value ?? "";
        });

    Api.gateways()
        .getKubernetesStatusGetById(row.gateway.id!)
        .find((res) => {
            const status = res[0]?.value as any;
            if (status && status.addresses) {
                const ip = status.addresses.find(
                    (a: any) => a.type === "IPAddress"
                );
                if (ip) {
                    row.address = ip.value;
                } else {
                    row.address = undefined;
                }
            } else {
                row.address = undefined;
            }
        });
}

function createItem() {
    bus.emit("gatewayCreate", {
        gateway: Gateway.Create(),
    });
}

function onEditItemBtnClicked(item: Row) {
    bus.emit("gatewayEdit", {
        gateway: item.gateway,
    });
}

function onDeleteItemBtnClicked(item: Row) {
    bus.emit("confirm", {
        body: `Do you want to delete <strong>${item.gateway.name}</strong>?`,
        confirmIcon: "fa fa-trash",
        confirmColor: "red",

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.gateways()
                    .deleteById(item.gateway.id!)
                    .delete(() => bus.emit("gatewaySaved"));
            }
        },
    });
}

function onPreviewBtnClicked(row: Row) {
    bus.emit("gatewayResourcePreview", {
        gateway: row.gateway,
    });
}

function onDeployBtnClicked(row: Row) {
    row.status = "loading";
    const api = Api.gateways().deployPutById(row.gateway.id!);
    api.setErrorHandler((response) => {
        let body = response;
        try {
            if (response.error) {
                body = JSON.parse(response.error);
            }
        } catch (e) {
            // Not JSON
        }

        bus.emit("json", {
            title: "Failed to deploy",
            body: body,
        });
        getItems();
        return false;
    });
    api.save(null, () => {
        getItems();
    });
}

function onTerminateBtnClicked(row: Row) {
    bus.emit("confirm", {
        body: `Do you want to terminate <strong>${row.gateway.name}</strong> from the cluster?`,
        confirmIcon: "fa fa-stop",
        confirmColor: "orange",

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                row.status = "loading";
                const api = Api.gateways().terminatePutById(row.gateway.id!);
                api.setErrorHandler((response) => {
                    let body = response;
                    try {
                        if (response.error) {
                            body = JSON.parse(response.error);
                        }
                    } catch (e) {
                        // Not JSON
                    }

                    bus.emit("json", {
                        title: "Failed to terminate",
                        body: body,
                    });
                    getItems();
                    return false;
                });
                api.save(null, () => {
                    getItems();
                });
            }
        },
    });
}

function onKubernetesStatusBtnClicked(row: Row) {
    Api.gateways()
        .getKubernetesStatusGetById(row.gateway.id!)
        .find((res) => {
            bus.emit("json", {
                title: "Kubernetes Status",
                body: res[0].value ?? "",
            });
        });
}

function onKubernetesEventsBtnClicked(row: Row) {
    Api.gateways()
        .getKubernetesEventsGetById(row.gateway.id!)
        .find((res) => {
            bus.emit("json", {
                title: "Kubernetes Events",
                body: res[0].value ?? "",
            });
        });
}

function onAddressClicked(address: string) {
    navigator.clipboard.writeText(address).then(() => {
        bus.emit("toast", {
            text: "Address copied to clipboard",
        });
    });
}
</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar density="compact" flat color="blue-grey lighten-5" dark>
            <v-toolbar-title>Gateways</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn
                small
                class=""
                @click="createItem()"
                prepend-icon="fa fa-plus"
            >
                Create
            </v-btn>
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
            "
        >
            <template v-slot:item.status="{ item }">
                <div class="cursor-pointer" @click="updateRowStatus(item)">
                    <v-icon
                        v-if="item.status === 'loading'"
                        class="fa-spin"
                        size="x-small"
                        >fa fa-spinner</v-icon
                    >
                    <v-icon
                        v-else-if="item.status === 'found'"
                        color="green"
                        size="x-small"
                        >fa fa-circle</v-icon
                    >
                    <v-icon
                        v-else-if="item.status === 'not-found'"
                        color="grey"
                        size="x-small"
                        >fa fa-circle</v-icon
                    >
                    <v-icon v-else color="red" size="x-small"
                        >fa fa-circle</v-icon
                    >

                    <v-tooltip activator="parent" location="bottom">{{
                        item.status
                    }}</v-tooltip>
                </div>
            </template>

            <template v-slot:item.address="{ item }">
                <v-chip
                    v-if="item.address"
                    size="x-small"
                    color="primary"
                    variant="outlined"
                    class="cursor-pointer"
                    prepend-icon="fa fa-copy"
                    @click="onAddressClicked(item.address)"
                >
                    {{ item.address }}
                    <v-tooltip activator="parent" location="bottom"
                        >Copy to clipboard</v-tooltip
                    >
                </v-chip>
                <span v-else>-</span>
            </template>

            <template v-slot:item.domains="{ item }">
                <v-menu open-on-hover location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-badge
                            v-if="item.gateway.domains?.length"
                            color="primary"
                            :content="item.gateway.domains.length"
                            inline
                            v-bind="props"
                            class="cursor-pointer"
                        ></v-badge>
                        <span v-else>-</span>
                    </template>
                    <v-list
                        v-if="item.gateway.domains?.length"
                        density="compact"
                    >
                        <v-list-item
                            v-for="domain in item.gateway.domains"
                            :key="domain.id"
                        >
                            <v-list-item-title>{{
                                domain.name
                            }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">
                    <v-btn
                        variant="plain"
                        color="blue-grey"
                        size="x-small"
                        icon
                        @click="onPreviewBtnClicked(item)"
                    >
                        <v-icon>fa fa-search</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Preview</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="green"
                        size="x-small"
                        icon
                        @click="onDeployBtnClicked(item)"
                    >
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Deploy</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="orange"
                        size="x-small"
                        icon
                        @click="onTerminateBtnClicked(item)"
                    >
                        <v-icon>fa fa-stop</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Terminate</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="blue-grey"
                        size="x-small"
                        icon
                        @click="onKubernetesStatusBtnClicked(item)"
                    >
                        <v-icon>fa fa-info-circle</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Kubernetes Status</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="blue-grey"
                        size="x-small"
                        icon
                        @click="onKubernetesEventsBtnClicked(item)"
                    >
                        <v-icon>fa fa-list</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Kubernetes Events</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="primary"
                        size="x-small"
                        icon
                        @click="onEditItemBtnClicked(item)"
                    >
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Edit</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="red"
                        size="x-small"
                        icon
                        @click="onDeleteItemBtnClicked(item)"
                    >
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Delete</v-tooltip
                        >
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
