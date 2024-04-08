<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {AutoUpdate, KeelHookQueueItem} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import debounce from "lodash.debounce";
import bus from "@/plugins/bus";

const props = defineProps<{
    showHeader: boolean;
}>();

const used = ref(false);
const showDialog = ref(false);
const itemCount = ref(0);
const rows = ref<AutoUpdate[]>([]);
const headers = ref([
    {title: 'Deployment', key: 'deployment', sortable: false},
    {title: 'Image', key: 'image', sortable: false},
    {title: 'Tag', key: 'tag', sortable: false},
    {title: 'Approved', key: 'approved', sortable: false},
    {title: 'Created', key: 'created', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(false);
const options = ref({});
// const wampSubscription1 = ref<WampSubscription>();
// const wampSubscription2 = ref<WampSubscription>();

const searchValue = ref('');

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    getItems(false, true);

    // wampSubscription1.value = WampService.subscribe(
    //     Events.KeelHookQueueItem_Created(),
    //     data => getItems(true, true)
    // );
    // wampSubscription2.value = WampService.subscribe(
    //     Events.KeelHookQueueItem_Deleted(),
    //     data => getItems(true, true)
    // );
});

onUnmounted(() => {
    // wampSubscription1.value?.unsubscribe();
    // wampSubscription2.value?.unsubscribe();
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

function getItems(doItems = true, doCount = false) {
    showDialog.value = true;

    // Get options from DataTable
    const tableOptions: any = options.value;

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.autoUpdates().get();

    if (searchValue.value?.length) {
        api
            .search('deployment.name', searchValue.value)
            .search('deployment.namespace', searchValue.value);
    }

    if (doItems) {
        api
            .include('deployment')
            .limit(tableOptions.itemsPerPage)
            .offset(tableOptions.itemsPerPage * (tableOptions.page - 1))
            .orderDesc('id')
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

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onApproveBtnClicked(row: AutoUpdate) {
    Api.autoUpdates().approvePutById(row.id!)
        .save(null, () => {
            getItems(true, true);
        });
}

function onShowLogsBtnClicked(row: AutoUpdate) {
    const code = row?.log?.trim().split('\n').map(line => {
        if (line.length) {
            return `<span>${line}</span>`;
        }
    }).join('');
    bus.emit('info', {
        title: 'Log',
        body: `<code class="line-numbers">${code}</code>`
    })
}

// </editor-fold>

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            v-if="props.showHeader"
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>Auto Updates</v-toolbar-title>

            <v-text-field
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
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

            <template v-slot:item.deployment="{ item }">
                <v-chip>{{ item.raw.deployment.name }}.{{ item.raw.deployment.namespace }}</v-chip>
            </template>

            <template v-slot:item.created="{ item }">
                <DateView :date-string="item.raw.created"/>
            </template>

            <template v-slot:item.tag="{ item }">
                {{ item.raw.previous_tag }} -> {{ item.raw.next_tag }}
            </template>

            <template v-slot:item.approved="{ item }">
                <DateView
                    v-if="item.raw.is_approved"
                    :date-string="item.raw.created"
                />
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">
                    <v-btn
                        v-if="!item.raw.is_approved"
                        variant="plain" color="primary" size="small" icon
                        @click="onApproveBtnClicked(item.raw)">
                        <v-icon>fa fa-check</v-icon>
                        <v-tooltip activator="parent" location="bottom">Approve</v-tooltip>
                    </v-btn>
                    <v-btn
                        v-if="item.raw.is_approved"
                        variant="plain" color="primary" size="small" icon
                        @click="onApproveBtnClicked(item.raw)">
                        <v-icon>fa fa-check</v-icon>
                        <v-tooltip activator="parent" location="bottom">Re-approve</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onShowLogsBtnClicked(item.raw)">
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Log</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>

    </div>
</template>

<style scoped>

</style>
