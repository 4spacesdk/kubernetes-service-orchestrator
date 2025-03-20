<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {Domain, System} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
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

const searchValue = ref('');

onMounted(() => {
    bus.on('domainSaved', onItemSaved);

    getItems(false, true);

    headers.value = [
        {title: 'Name', key: 'domain.name', sortable: false},
        {title: 'Certificate', key: 'certificate', sortable: false},
        ...(System.Instance.is_network_istio_supported ? [{
            title: 'Istio Gateway',
            key: 'istio_gateway',
            sortable: false
        }] : []),
        ...(System.Instance.is_network_contour_supported ? [{title: 'Contour', key: 'contour', sortable: false}] : []),
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
    // Get options from DataTable
    const tableOptions: any = options.value;

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.domains().get();

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('certificate_name', searchValue.value);
    }

    if (doItems) {
        api
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderAsc('name')
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
    bus.emit('domainCreate', Domain.Create());
}

function onEditItemBtnClicked(item: Row) {
    bus.emit('domainEdit', {
        domain: item.domain,
    });
}

function onDeleteItemBtnClicked(item: Row) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.domain.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

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
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>Domains</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn small class="" @click="createItem()"
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
            @update:options="options = $event; getItems()">
            <template v-slot:item.istio_gateway="{ item }">
                <span v-if="item.domain.enable_istio_gateway">Enabled</span>
            </template>
            <template v-slot:item.contour="{ item }">
                <span v-if="item.domain.enable_contour">Enabled</span>
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

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">

                    <v-menu
                        min-width="250">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary" size="small" icon>
                                <v-icon>fa fa-cog</v-icon>
                                <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                            </v-btn>
                        </template>
                        <domain-edit-button
                            :domain="item.domain"/>
                    </v-menu>

                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onEditItemBtnClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="red" size="small" icon
                        @click="onDeleteItemBtnClicked(item)">
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
