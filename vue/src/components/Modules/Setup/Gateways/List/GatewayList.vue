<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import { Gateway } from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import { CopyNameStrategy, duplicateEntity } from "@/helpers/DuplicateEntity";

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
    {
        title: string;
        key: string;
        sortable: boolean;
        align?: "end" | "center" | "start" | undefined;
    }[]
>([]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"gateway.name": "name", "gateway.gateway_class_name": "gateway_class_name", "gateway.namespace": "namespace"},
    defaultSort: {key: "gateway.name", order: "asc"},
});

onMounted(() => {
    bus.on("gatewaySaved", onItemSaved);

    getItems(false, true);

    headers.value = [
        { title: "Status", key: "status", sortable: false, align: "center" },
        { title: "Name", key: "gateway.name", sortable: true },
        { title: "Address", key: "address", sortable: false },
        { title: "Class", key: "gateway.gateway_class_name", sortable: true },
        { title: "Namespace", key: "gateway.namespace", sortable: true },
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
        applyPaging(api);
        applyOrdering(api);
        api
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

function onDuplicateItemBtnClicked(item: Row) {
    // Read the row again rather than copying what the table holds, so fields the list
    // does not ask for still make it into the copy. A gateway name has to stay a valid
    // DNS-1123 label, so the copy is suffixed rather than labelled.
    Api.gateways().getById(item.gateway.id!).find(items => {
        const copy = duplicateEntity(items[0], Gateway, CopyNameStrategy.Identifier);

        // Addresses are not copied. An address names one concrete cluster address, such as
        // a reserved static ip, and two gateways cannot both hold it - the copy needs its
        // own. Leaving them out also keeps the copy clear of the double write described in
        // GatewayEditDialog, where a posted address relation is written again by
        // updateGatewayAddresses.
        copy.gateway_addresses = undefined;

        bus.emit("gatewayEdit", { gateway: copy });
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

            <v-text-field data-shortcut="search"
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn data-shortcut="create"
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
            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            v-model:sort-by="sortBy"
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

            <template v-slot:item.gateway.name="{ item }">

                <name-link @click="onEditItemBtnClicked(item)">{{ item.gateway.name }}</name-link>

            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <v-btn
                        variant="plain"
                        color="blue-grey"
                        @click="onPreviewBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-search</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Preview</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="green"
                        @click="onDeployBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-play</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Deploy</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="blue-grey"
                        @click="onKubernetesStatusBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-info-circle</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Kubernetes Status</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="blue-grey"
                        @click="onKubernetesEventsBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-list</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Kubernetes Events</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="primary"
                        @click="onEditItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Edit</v-tooltip
                        >
                    </v-btn>

                    <v-btn
                        variant="plain"
                        color="primary"
                        @click="onDuplicateItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-clone</v-icon>
                        <v-tooltip activator="parent" location="bottom"
                            >Duplicate</v-tooltip
                        >
                    </v-btn>

                    <!-- Terminate and Delete stay out of reach of a slip from Deploy (LIST-3). -->
                    <v-menu location="bottom end">
                        <template v-slot:activator="{ props }">
                            <v-btn v-bind="props" variant="plain" color="primary"  aria-label="More" size="small" density="comfortable" icon>
                                <v-icon>fa fa-ellipsis-vertical</v-icon>
                            </v-btn>
                        </template>
                        <v-list density="compact">
                            <v-list-item prepend-icon="fa fa-stop" title="Terminate" base-color="orange" @click="onTerminateBtnClicked(item)" />
                            <v-list-item prepend-icon="fa fa-trash" title="Delete" base-color="red" @click="onDeleteItemBtnClicked(item)" />
                        </v-list>
                    </v-menu>
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
