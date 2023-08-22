<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DeploymentSpecification, DeploymentSpecificationIngressRulePath} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DeploymentSpecificationUpdateIngressesDialog_Input {
    deploymentSpecification: DeploymentSpecification;
}

interface Row {
    ingressClass?: string;
    proxyBodySize?: number;
    proxyConnectTimeout?: number;
    proxyReadTimeout?: number;
    proxySendTimeout?: number;
    sslRedirect?: boolean;
    enableTls?: boolean;

    paths?: {
        path?: string;
        pathType?: string;
        backendServicePortName?: string;
    }[];
}

const props = defineProps<{ input: DeploymentSpecificationUpdateIngressesDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Ingress class', key: 'ingressClass', sortable: false},
    {title: 'Proxy body size', key: 'proxyBodySize', sortable: false},
    {title: 'Proxy body timeout', key: 'proxyConnectTimeout', sortable: false},
    {title: 'Proxy read timeout', key: 'proxyReadTimeout', sortable: false},
    {title: 'Proxy send timeout', key: 'proxySendTimeout', sortable: false},
    {title: 'SSL Redirect', key: 'sslRedirect', sortable: false},
    {title: 'TLS', key: 'enableTls', sortable: false},
    {title: 'Rule paths', key: 'paths', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isSaving = ref(false);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

onUnmounted(() => {
});

function render() {
    showDialog.value = true;

    isLoading.value = true;
    Api.deploymentSpecifications().get()
        .where('id', props.input.deploymentSpecification.id!)
        .include('deployment_specification_ingress?include=deployment_specification_ingress_rule_path')
        .find(value => {
            rows.value = value[0].deployment_specification_ingresses
                ?.map(ingress => {
                    return {
                        ingressClass: ingress.ingress_class ?? '',
                        proxyBodySize: ingress.proxy_body_size ?? 0,
                        proxyConnectTimeout: ingress.proxy_connect_timeout ?? 0,
                        proxyReadTimeout: ingress.proxy_read_timeout ?? 0,
                        proxySendTimeout: ingress.proxy_send_timeout ?? 0,
                        sslRedirect: ingress.ssl_redirect ?? 0,
                        enableTls: ingress.enable_tls ?? false,
                        paths: ingress.deployment_specification_ingress_rule_paths?.map((path: DeploymentSpecificationIngressRulePath) => {
                            return {
                                path: path.path,
                                pathType: path.path_type,
                                backendServicePortName: path.backend_service_port_name,
                            }
                        }) ?? [],
                    }
                }) ?? [];
            itemCount.value = rows.value.length;
            isLoading.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCreateBtnClicked() {
    const newItem = {

    };
    bus.emit('deploymentSpecificationUpdateIngress', {
        ingress: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdateIngress', {
        ingress: row,
        onSaveCallback: () => {

        }
    });
}

function onRulePathsBtnClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdateIngressRulePaths', {
        deploymentSpecification: props.input.deploymentSpecification,
        items: row.paths?.map(path => {
            const rulePath = new DeploymentSpecificationIngressRulePath();
            rulePath.path = path.path;
            rulePath.path_type = path.pathType;
            rulePath.backend_service_port_name = path.backendServicePortName;
            return rulePath;
        }),
        onSaveCallback: (items: DeploymentSpecificationIngressRulePath[]) => {
            row.paths = items?.map((path: DeploymentSpecificationIngressRulePath) => {
                return {
                    path: path.path,
                    pathType: path.path_type,
                    backendServicePortName: path.backend_service_port_name,
                }
            }) ?? [];
        }
    });
}

function onDeleteRowClicked(row: Row) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.deploymentSpecifications()
        .updateIngressesPutById(props.input.deploymentSpecification.id!);
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
        isSaving.value = false;
        close();
    });
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        v-model="showDialog">
        <v-card
            :loading="isLoading"
            :disabled="isLoading"
            class="w-100 h-100">
            <v-card-title>
                <div class="d-flex w-100">
                    <span class="my-auto">Ingresses</span>
                    <v-chip class="my-auto mx-auto">{{ props.input.deploymentSpecification.name }}</v-chip>

                    <div class="my-auto ml-auto d-flex justify-end gap-1">
                        <v-btn
                            icon
                            variant="plain"
                            color="secondary"
                            size="small"
                            @click="onCreateBtnClicked()">
                            <v-icon>fa fa-plus</v-icon>
                            <v-tooltip activator="parent" location="bottom">Create</v-tooltip>
                        </v-btn>
                    </div>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <v-data-table-server
                    :headers="headers"
                    :items-length="itemCount"
                    :items="rows"
                    :items-per-page="-1"
                    class="table"
                    density="compact">
                    <template v-slot:item.sslRedirect="{ item }">
                        <v-icon v-if="item.raw.sslRedirect">fa fa-check</v-icon>
                    </template>
                    <template v-slot:item.enableTls="{ item }">
                        <v-icon v-if="item.raw.enableTls">fa fa-check</v-icon>
                    </template>
                    <template v-slot:item.paths="{ item }">
                        <span v-if="item.raw.paths">{{ item.raw.paths.length }}</span>
                    </template>
                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end">
                            <v-btn
                                variant="plain" color="primary" size="small"
                                @click="onRulePathsBtnClicked(item.raw)">
                                <v-icon>fa fa-link</v-icon>
                                <v-tooltip activator="parent" location="bottom">Rule paths</v-tooltip>
                            </v-btn>
                            <v-btn
                                variant="plain" color="primary" size="small"
                                @click="onEditRowClicked(item.raw)">
                                <v-icon>fa fa-pen</v-icon>
                                <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                            </v-btn>
                            <v-btn
                                variant="plain"
                                color="red" size="small"
                                @click="onDeleteRowClicked(item.raw)">
                                <v-icon>fa fa-trash</v-icon>
                                <v-tooltip activator="parent" location="bottom">Delete</v-tooltip>
                            </v-btn>
                        </div>
                    </template>
                </v-data-table-server>
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Close
                </v-btn>

                <v-btn
                    :loading="isSaving"
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="green"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}
</style>
