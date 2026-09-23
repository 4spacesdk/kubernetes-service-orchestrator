<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {NetworkTypes} from "@/constants";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

interface Row {
    path: string;
    port: number;
    protocol: string;
    timeoutPolicyIdle: string;
    timeoutPolicyResponse: string;
    timeoutPolicyIdleConnection: string;
}

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const title = computed(() => {
    return props.deploymentSpecification.network_type === NetworkTypes.GatewayApi ? 'Http Routes' : 'Http Proxy Routes';
});

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = computed(() => {
    const items = [
        {title: 'Path', key: 'path', sortable: false},
        {title: 'Port', key: 'port', sortable: false},
    ];

    if (props.deploymentSpecification.network_type === NetworkTypes.Contour) {
        items.push(
            {title: 'Protocol', key: 'protocol', sortable: false},
            {title: 'Timeout Policy, Idle', key: 'timeoutPolicyIdle', sortable: false},
            {title: 'Timeout Policy, Response', key: 'timeoutPolicyResponse', sortable: false},
            {title: 'Timeout Policy, Idle Connection', key: 'timeoutPolicyIdleConnection', sortable: false},
        );
    }

    items.push({title: '', key: 'actions', sortable: false});

    return items;
});
const isSaving = ref(false);

const {markSaved} = useUnsavedChanges(() => rows.value);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deploymentSpecifications().get()
        .where('id', props.deploymentSpecification.id!)
        .include('deployment_specification_http_proxy_route')
        .find(value => {
            rows.value = value[0].deployment_specification_http_proxy_routes
                ?.map(item => {
                    return {
                        path: item.path ?? '',
                        port: item.port ?? 0,
                        protocol: item.protocol ?? '',
                        timeoutPolicyIdle: item.timeout_policy_idle ?? '',
                        timeoutPolicyResponse: item.timeout_policy_response ?? '',
                        timeoutPolicyIdleConnection: item.timeout_policy_idle_connection ?? '',
                    }
                }) ?? [];
            itemCount.value = rows.value.length;
            isLoading.value = false;
            markSaved();
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCreateBtnClicked() {
    const newItem = {
        path: '/',
        port: 80,
        protocol: props.deploymentSpecification.network_type === NetworkTypes.Contour ? 'h2c' : '',
        timeoutPolicyIdle: '',
        timeoutPolicyResponse: '',
        timeoutPolicyIdleConnection: '',
    };
    bus.emit('deploymentSpecificationUpdateHttpProxyRoute', {
        networkType: props.deploymentSpecification.network_type ?? '',
        httpProxyRoute: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdateHttpProxyRoute', {
        networkType: props.deploymentSpecification.network_type ?? '',
        httpProxyRoute: row,
        onSaveCallback: () => {

        }
    });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSave() {
    if (isSaving.value) {
        return;
    }
    isSaving.value = true;
    const api = Api.deploymentSpecifications().updateDeploymentHttpProxyRoutesPutById(props.deploymentSpecification.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save({
        values: rows.value
    }, newItem => {
        bus.emit('deploymentSpecificationSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
        isSaving.value = false;
        render();
    });
}

// </editor-fold>

</script>

<template>
    <page-section
        :title="title"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <template #actions>
            <v-btn
                :icon="true"
                variant="plain"
                color="secondary"
                size="small"
                @click="onCreateBtnClicked()">
                <v-icon>fa fa-plus</v-icon>
                <v-tooltip activator="parent" location="bottom">Create</v-tooltip>
            </v-btn>
        </template>

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :items-per-page="-1"
            class="table"
            density="compact">
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end gap-1">
                    <v-btn
                        variant="plain" color="primary" size="small"
                        @click="onEditRowClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain"
                        color="error" size="small"
                        @click="onDeleteRowClicked(item)">
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>
    </page-section>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}
</style>
