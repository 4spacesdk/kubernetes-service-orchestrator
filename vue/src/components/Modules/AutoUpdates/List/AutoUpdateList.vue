<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {AutoUpdate} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import DateView from "@/components/Modules/Common/DateView.vue";
import debounce from "lodash.debounce";
import bus from "@/plugins/bus";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";

const props = defineProps<{
    showHeader: boolean;
}>();

interface Row {
    selectable: boolean;
    item: AutoUpdate;
    isLoadingAccept: boolean;
}

const used = ref(false);
const showDialog = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Deployment', key: 'deployment', sortable: false},
    {title: 'Tag', key: 'tag', sortable: false},
    {title: 'Created', key: 'created', sortable: false},
    {title: 'Approved', key: 'approved', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isLoading = ref(false);
const options = ref({});
const wampSubscription1 = ref<WampSubscription>();
const wampSubscription2 = ref<WampSubscription>();
const selectedRows = ref<Row[]>([]);
const isLoadingBatchApprove = ref(false);

const searchValue = ref('');

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    getItems(false, true);

    wampSubscription1.value = WampService.subscribe(
        Events.AutoUpdate_Created(),
        data => getItems(true, true)
    );
    wampSubscription2.value = WampService.subscribe(
        Events.AutoUpdate_RolledOut(),
        data => getItems(true, true)
    );
});

onUnmounted(() => {
    wampSubscription1.value?.unsubscribe();
    wampSubscription2.value?.unsubscribe();
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
                rows.value = items.map(item => {
                    return {
                        selectable: item.is_approved !== true,
                        item: item,
                        isLoadingAccept: false,
                    };
                });
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

function onApproveBtnClicked(row: Row, onFinish?: () => void, onError?: () => void) {
    const api = Api.autoUpdates().approvePutById(row.item.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            try {
                bus.emit('json', {
                    title: `Failed to approve ${row.item.deployment?.name}.${row.item.deployment?.namespace}`,
                    body: JSON.parse(response.error),
                });
            } catch (e) {
                bus.emit('info', {
                    title: `Failed to approve ${row.item.deployment?.name}.${row.item.deployment?.namespace}`,
                    body: response.error
                });
            }
        }
        row.isLoadingAccept = false;
        if (onError) {
            onError();
        }
        return false;
    });
    api.save(null, () => {
        getItems(true, true);
        if (onFinish) {
            onFinish();
        }
    });
}

function onDeleteBtnClicked(row: Row) {
    bus.emit('confirm', {
        body: `Do you want to delete this update?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.autoUpdates().deleteById(row.item.id!).delete(() => getItems(true, true));
            }
        }
    });
}

function onShowLogsBtnClicked(row: Row) {
    const code = row.item?.log
        ?.trim()
        ?.split('\n')
        ?.map(line => `<span class="d-block">${line}</span>`)
        ?.join('') ?? '';

    bus.emit('info', {
        title: 'Log',
        body: `<code class="line-numbers">${code}</code>`
    })
}

function onAcceptSelectedBtnClicked() {
    isLoadingBatchApprove.value = true;

    let index = 0;
    const onError = () => {
        isLoadingBatchApprove.value = false;
    };
    const onContinue = () => {
        if (selectedRows.value.length == index) {
            isLoadingBatchApprove.value = false;
            return;
        }
        setTimeout(() => runRow(selectedRows.value[index++]), 200);
    };
    const runRow = (row: Row) => {
        onApproveBtnClicked(
            row,
            () => {
                onContinue();
            },
            () => {
                onError();
            }
        );
    };
    onContinue();
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
            class="pr-2"
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

            <v-btn
                :disabled="selectedRows.length == 0"
                variant="outlined" color="success" size="small"
                @click="onAcceptSelectedBtnClicked()"
                :loading="isLoadingBatchApprove"
            >
                <v-icon>fa fa-check</v-icon>
                <v-tooltip activator="parent" location="bottom">Approve</v-tooltip>
            </v-btn>
        </v-toolbar>

        <v-data-table-server
            v-model="selectedRows"
            item-selectable="selectable"
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :loading="isLoading"
            :items-per-page="50"
            :show-select="true"
            return-object
            class="table"
            density="compact"
            @update:options="options = $event; getItems()">

            <template v-slot:item.deployment="{ item }">
                <v-chip v-if="item.item.deployment">{{ item.item.deployment.name }}.{{
                        item.item.deployment.namespace
                    }}
                </v-chip>
            </template>

            <template v-slot:item.created="{ item }">
                <DateView :date-string="item.item.created"/>
            </template>

            <template v-slot:item.tag="{ item }">
                <div class="d-flex align-center">
                    <span>{{ item.item.previous_tag }}</span>
                    <v-icon size="small" class="mx-1">fa fa-arrow-right</v-icon>
                    <span>{{ item.item.next_tag }}</span>
                </div>
            </template>

            <template v-slot:item.approved="{ item }">
                <DateView
                    v-if="item.item.is_approved"
                    :date-string="item.item.approved_date"
                />
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">
                    <v-btn
                        v-if="!item.item.is_approved"
                        variant="plain" color="success" size="small" :icon="true"
                        @click="onApproveBtnClicked(item)"
                        :loading="item.isLoadingAccept"
                    >
                        <v-icon>fa fa-check</v-icon>
                        <v-tooltip activator="parent" location="bottom">Approve</v-tooltip>
                    </v-btn>
                    <v-btn
                        v-if="item.item.is_approved"
                        variant="plain" color="grey" size="small" :icon="true"
                        @click="onApproveBtnClicked(item)"
                        :loading="item.isLoadingAccept"
                    >
                        <v-icon>fa fa-check</v-icon>
                        <v-tooltip activator="parent" location="bottom">Re-approve</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small" :icon="true"
                        @click="onShowLogsBtnClicked(item)">
                        <v-icon>fa fa-rectangle-list</v-icon>
                        <v-tooltip activator="parent" location="bottom">Log</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="red" size="small" :icon="true"
                        @click="onDeleteBtnClicked(item)">
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>

    </div>
</template>

<style scoped>

</style>
