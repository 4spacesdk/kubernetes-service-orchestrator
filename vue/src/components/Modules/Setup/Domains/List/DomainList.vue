<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {Domain} from "@/core/services/Deploy/models";
import debounce from "lodash.debounce";

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: Domain): void
}>();

const itemCount = ref(0);
const rows = ref<Domain[]>([]);
const headers = ref([
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Certificate', key: 'certificate_name', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(true);
const options = ref({});

const searchValue = ref('');

onMounted(() => {
    bus.on('domainSaved', onItemSaved);

    getItems(false, true);
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

function deleteItem(item: Domain) {
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
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">

                    <v-btn
                        variant="plain" color="primary" size="small"
                        @click="applyCertificate(item.raw)">

                        <v-icon>fa fa-certificate</v-icon>
                        <v-tooltip activator="parent" location="bottom">Apply certificate</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" size="small"
                        @click="getCertificateEvents(item.raw)">
                        <v-icon>fa fa-terminal</v-icon>
                        <v-tooltip activator="parent" location="bottom">Events</v-tooltip>
                    </v-btn>

                    <v-btn
                        variant="plain" color="primary" size="small"
                        @click="getCertificateStatus(item.raw)">
                        <v-icon>fa fa-signal</v-icon>
                        <v-tooltip activator="parent" location="bottom">Status</v-tooltip>
                    </v-btn>

                    <v-btn variant="plain" color="red" size="small" @click="deleteItem(item.raw)">
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
