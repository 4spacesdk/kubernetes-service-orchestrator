<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {DeploymentSpecification, System} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {HealthCheckTypes, HostingProviders, NetworkTypes} from "@/constants";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

interface Row {
    protocol?: string;
    name?: string;
    port?: number;
    targetPort?: number;
    healthCheckType?: string;
    healthCheckPath?: string;
}

interface Header {
    title: string,
    key: string,
    sortable: boolean
}

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref<Header[]>([]);
const isSaving = ref(false);
const showHealthCheck = ref(false);

const {markSaved} = useUnsavedChanges(() => rows.value);

/**
 * One HealthCheckPolicy covers every port of the service, so a port that cannot answer HTTP drags the
 * whole service onto a TCP check. Spell the outcome out rather than letting it surprise people later.
 */
const healthCheckSummary = computed(() => {
    if (!showHealthCheck.value) {
        return null;
    }

    const types = rows.value.map(row => row.healthCheckType);
    if (types.includes(HealthCheckTypes.Tcp)) {
        return types.includes(HealthCheckTypes.Http)
            ? 'TCP health check for the whole service. The HTTP ports are covered by it too, so their path is ignored.'
            : 'TCP health check for the whole service.';
    }
    if (types.includes(HealthCheckTypes.Http)) {
        const path = rows.value.find(row => row.healthCheckType === HealthCheckTypes.Http && row.healthCheckPath)?.healthCheckPath;
        return `HTTP health check for the whole service, on ${path ?? '/'}.`;
    }
    return 'No HealthCheckPolicy. GKE falls back to its default HTTP check, which a websocket port will fail.';
});

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    showHealthCheck.value = System.Instance.hosting_provider === HostingProviders.Gke
        && props.deploymentSpecification.network_type === NetworkTypes.GatewayApi;

    headers.value = [
        {title: 'Protocol', key: 'protocol', sortable: false},
        {title: 'Name', key: 'name', sortable: false},
        {title: 'Port', key: 'port', sortable: false},
        {title: 'Target Port', key: 'targetPort', sortable: false},
    ];
    if (showHealthCheck.value) {
        headers.value.push({title: 'Health Check', key: 'healthCheckType', sortable: false});
    }
    headers.value.push({title: '', key: 'actions', sortable: false});

    isLoading.value = true;
    Api.deploymentSpecifications().get()
        .where('id', props.deploymentSpecification.id!)
        .include('deployment_specification_service_port')
        .find(value => {
            rows.value = value[0].deployment_specification_service_ports
                ?.map(servicePort => {
                    return {
                        protocol: servicePort.protocol ?? '',
                        name: servicePort.name ?? '',
                        port: servicePort.port,
                        targetPort: servicePort.target_port,
                        healthCheckType: servicePort.health_check_type ?? '',
                        healthCheckPath: servicePort.health_check_path ?? '',
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
    };
    bus.emit('deploymentSpecificationUpdateServicePort', {
        deploymentSpecification: props.deploymentSpecification,
        servicePort: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdateServicePort', {
        deploymentSpecification: props.deploymentSpecification,
        servicePort: row,
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
    const api = Api.deploymentSpecifications().updateServicePortsPutById(props.deploymentSpecification.id!);
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
        title="Service Ports"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <template #actions>
            <v-btn
                icon
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
            <template v-slot:item.healthCheckType="{ item }">
                <span v-if="item.healthCheckType === HealthCheckTypes.Tcp">TCP</span>
                <span v-else-if="item.healthCheckType === HealthCheckTypes.Http">
                    HTTP {{ item.healthCheckPath || '/' }}
                </span>
                <span v-else class="text-disabled">Default</span>
            </template>
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
                        color="red" size="small"
                        @click="onDeleteRowClicked(item)">
                        <v-icon>fa fa-trash</v-icon>
                        <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                    </v-btn>
                </div>
            </template>
        </v-data-table-server>

        <v-alert
            v-if="healthCheckSummary"
            class="mt-2"
            type="info"
            variant="tonal"
            density="compact">
            {{ healthCheckSummary }}
        </v-alert>
    </page-section>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}
</style>
