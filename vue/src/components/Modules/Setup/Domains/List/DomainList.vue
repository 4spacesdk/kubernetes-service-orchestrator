<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {Domain, System} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import { CopyNameStrategy, duplicateEntity } from "@/helpers/DuplicateEntity";
import DomainEditButton from "@/components/Modules/Setup/Domains/EditButton/DomainEditButton.vue";
import DateView from "@/components/Modules/Common/DateView.vue";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: Domain): void
}>();

interface Row {
    domain: Domain;
    certificate?: {
        renewalDate?: Date;
        notBeforeDate?: Date;
        notAfterDate?: Date;
    },
    isLoadingCertificate?: boolean;
}

const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref<{ title: string, key: string, sortable: boolean }[]>([]);
const isLoading = ref(true);
const options = ref({});

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"domain.name": "name"},
    defaultSort: {key: "domain.name", order: "asc"},
});

onMounted(() => {
    bus.on('domainSaved', onItemSaved);

    getItems(false, true);

    headers.value = [
        {title: 'Name', key: 'domain.name', sortable: true},
        {title: 'Certificate', key: 'certificate', sortable: false},
        ...(System.Instance.is_network_istio_supported ? [{
            title: 'Istio Gateway',
            key: 'istio_gateway',
            sortable: false
        }] : []),
        ...(System.Instance.is_network_contour_supported ? [{title: 'Contour', key: 'contour', sortable: false}] : []),
        ...(System.Instance.is_network_gateway_api_supported ? [{title: 'Gateway', key: 'gateway', sortable: false}] : []),
        {title: '', key: 'actions', sortable: false},
    ];
});

onUnmounted(() => {
    bus.off('domainSaved', onItemSaved);
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

function onItemSaved() {
    getItems(true, true);
}

function getItems(doItems = true, doCount = false) {

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.domains().get()
        .include('gateway');

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('certificate_name', searchValue.value);
    }

    if (doItems) {
        applyPaging(api);
        applyOrdering(api);
        api
            .find(items => {
                rows.value = items.map(item => ({domain: item}));
                isLoading.value = false;
            });
    }

    // Count total amount of items
    if (doCount) {
        api.count(count => {
            itemCount.value = count;
        });
    }
}

// <editor-fold desc="View functions">

function createItem() {
    bus.emit('domainCreate', {});
}

function onDuplicateItemBtnClicked(item: Row) {
    // Read the row again rather than copying what the table holds, so fields the list
    // does not ask for still make it into the copy.
    Api.domains().getById(item.domain.id!).find(items => {
        const copy = duplicateEntity(items[0], Domain, CopyNameStrategy.Clear);

        // Relations are not part of a copy. The gateway is picked again in the dialog,
        // and workspaces belong to the original domain.
        copy.gateway = undefined;
        copy.workspaces = undefined;

        // A domain name cannot be derived from another domain name, so the field is left
        // empty and the dialog opens with the cursor work still to do.
        bus.emit('domainCreate', { domain: copy });
    });
}

function onEditItemBtnClicked(item: Row) {
    bus.emit('domainEdit', {
        domain: item.domain,
    });
}

function onDeleteItemBtnClicked(item: Row) {
    bus.emit('confirm', {
        body: `Do you want to delete "${item.domain.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'error',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.domains().deleteById(item.domain.id!).delete(() => bus.emit('domainSaved'));
            }
        }
    });
}

function onCertificateClicked(item: Row) {
    item.isLoadingCertificate = true;
    const api = Api.domains().getCertificateStatusGetById(item.domain.id!);
    api.setErrorHandler(error => {
        item.isLoadingCertificate = false;
        return true;
    });
    api.find(response => {
        if (response.length == 1 && response[0].conditions?.length) {
            const status = response[0]!;
            item.certificate = {
                renewalDate: status.renewalTime ? new Date(status.renewalTime) : undefined,
                notBeforeDate: status.notBefore ? new Date(status.notBefore) : undefined,
                notAfterDate: status.notAfter ? new Date(status.notAfter) : undefined,
            };
            item.isLoadingCertificate = false;
        }
        item.isLoadingCertificate = false;
    });
}

// </editor-fold>

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            density="compact"
            flat
            color="toolbar"
            dark
        >
            <v-toolbar-title>Domains</v-toolbar-title>

            <v-text-field data-shortcut="search"
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn data-shortcut="create" small class="" @click="createItem()"
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
            @update:options="options = $event; getItems()">
            <template v-slot:item.istio_gateway="{ item }">
                <span v-if="item.domain.enable_istio_gateway">Enabled</span>
            </template>
            <template v-slot:item.contour="{ item }">
                <span v-if="item.domain.enable_contour">Enabled</span>
            </template>
            <template v-slot:item.gateway="{ item }">
                <span v-if="item.domain.gateway">{{ item.domain.gateway.name }}</span>
            </template>

            <template v-slot:item.certificate="{ item }">
                <v-menu
                    min-width="250">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            v-if="item.domain.certificate_name"
                            variant="text"
                            size="small"
                            class="px-0"
                            :loading="item.isLoadingCertificate"
                            @click="onCertificateClicked(item)"
                        >
                            {{ item.domain.certificate_name }}
                        </v-btn>
                    </template>
                    <v-card
                        style="width: 250px;"
                        class="pa-4"
                    >
                        <div class="d-flex flex-column ga-2">
                            <div
                                v-if="item.certificate?.renewalDate"
                                class="d-flex justify-space-between"
                            >
                                <span>Renewal</span>
                                <DateView
                                    :date="item.certificate.renewalDate"
                                    textFormat="DD/MM-YY HH:mm:ss"
                                />
                            </div>
                            <div
                                v-if="item.certificate?.notBeforeDate"
                                class="d-flex justify-space-between"
                            >
                                <span>Not before</span>
                                <DateView
                                    :date="item.certificate.notBeforeDate"
                                    textFormat="DD/MM-YY HH:mm:ss"
                                />
                            </div>
                            <div
                                v-if="item.certificate?.notAfterDate"
                                class="d-flex justify-space-between"
                            >
                                <span>Not after</span>
                                <DateView
                                    :date="item.certificate.notAfterDate"
                                    textFormat="DD/MM-YY HH:mm:ss"
                                />
                            </div>
                        </div>
                        <span v-if="!item.certificate && !item.isLoadingCertificate">Failed to get certificate status</span>
                    </v-card>
                </v-menu>
            </template>

            <template v-slot:item.domain.name="{ item }">

                <name-link @click="onEditItemBtnClicked(item)">{{ item.domain.name }}</name-link>

            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">

                    <v-menu
                        min-width="250">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary"
                                size="small"
                                density="comfortable"
                                icon
                            >
                                <v-icon>fa fa-cog</v-icon>
                                <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                            </v-btn>
                        </template>
                        <domain-edit-button
                            :domain="item.domain"/>
                    </v-menu>

                    <v-btn
                        variant="plain" color="primary" 
                        @click="onEditItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" 
                        @click="onDuplicateItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-clone</v-icon>
                        <v-tooltip activator="parent" location="bottom">Duplicate</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="error" 
                        @click="onDeleteItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
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
