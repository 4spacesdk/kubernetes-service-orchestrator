<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {Domain, System} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";
import DeploymentEditButton from "@/components/Modules/Setup/Deployments/EditButton/DeploymentEditButton.vue";
import DomainEditButton from "@/components/Modules/Setup/Domains/EditButton/DomainEditButton.vue";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: Domain): void
}>();

const itemCount = ref(0);
const rows = ref<Domain[]>([]);
const headers = ref<{title: string, key: string, sortable: boolean}[]>([]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

onMounted(() => {
    bus.on('domainSaved', onItemSaved);

    getItems(false, true);

    headers.value = [
        {title: 'Name', key: 'name', sortable: false},
        {title: 'Certificate', key: 'certificate_name', sortable: false},
        ...(System.Instance.is_network_istio_supported ? [{title: 'Istio Gateway', key: 'istio_gateway', sortable: false}] : []),
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
                rows.value = items;
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
    bus.emit('domainCreate', new Domain());
}

function onEditItemBtnClicked(item: Domain) {
    bus.emit('domainEdit', {
        domain: item,
    });
}

function onDeleteItemBtnClicked(item: Domain) {
    bus.emit('confirm', {
        body: `Do you want to delete <strong>${item.name}</strong>?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.domains().deleteById(item.id!).delete(() => bus.emit('domainSaved'));
            }
        }
    });
}

function applyCertificate(item: Domain) {
    Api.domains().applyCertificatePutById(item.id!)
        .save(() => {

        });
}

function getCertificateEvents(item: Domain) {
    Api.domains().getCertificateEventsGetById(item.id!)
        .find(events => {
            bus.emit('info', {
                title: item.name,
                body: events.length
                    ? events.map(event => `${event.age}: ${event.message}`).join('<br>')
                    : 'No events found',
            });
        });
}

function getCertificateStatus(item: Domain) {
    Api.domains().getCertificateStatusGetById(item.id!)
        .find(response => {
            if (response.length == 1 && response[0].conditions?.length) {
                const status = response[0]!;
                const lines = [
                    `Renewal: ${status.renewalTime}`,
                    `Not before: ${status.notBefore}`,
                    `Not after: ${status.notAfter}`,
                    '',
                    '<strong>Conditions</strong>'
                ];
                lines.push(...status.conditions!
                    .map(condition => {
                        return `${condition.lastTransitionTime}: ${condition.type}, ${condition.reason}, ${condition.message}`;
                    })
                );
                bus.emit('info', {
                    title: item.name,
                    body: lines.join('<br>')
                });
            } else {
                bus.emit('info', {
                    title: item.name,
                    body: 'Certificate not found',
                });
            }
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
                <span v-if="item.enable_istio_gateway">Enabled</span>
            </template>
            <template v-slot:item.contour="{ item }">
                <span v-if="item.enable_contour">Enabled</span>
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
                            :domain="item"/>
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
