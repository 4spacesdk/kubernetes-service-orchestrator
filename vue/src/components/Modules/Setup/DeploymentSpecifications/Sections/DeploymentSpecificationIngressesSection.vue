<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {
    DeploymentSpecification,
    DeploymentSpecificationIngressAnnotation,
    DeploymentSpecificationIngressRulePath
} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

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

    annotations?: {
        name?: string;
        value?: string;
    }[];
}

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

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

const {markSaved} = useUnsavedChanges(() => rows.value);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deploymentSpecifications().get()
        .where('id', props.deploymentSpecification.id!)
        .include(`deployment_specification_ingress?include=${encodeURIComponent('deployment_specification_ingress_rule_path,deployment_specification_ingress_annotation')}`)
        .find(value => {
            rows.value = value[0].deployment_specification_ingresses
                ?.map(ingress => {
                    return {
                        ingressClass: ingress.ingress_class ?? '',
                        proxyBodySize: ingress.proxy_body_size ?? 0,
                        proxyConnectTimeout: ingress.proxy_connect_timeout ?? 0,
                        proxyReadTimeout: ingress.proxy_read_timeout ?? 0,
                        proxySendTimeout: ingress.proxy_send_timeout ?? 0,
                        sslRedirect: ingress.ssl_redirect ?? false,
                        enableTls: ingress.enable_tls ?? false,
                        paths: ingress.deployment_specification_ingress_rule_paths?.map((path: DeploymentSpecificationIngressRulePath) => {
                            return {
                                path: path.path,
                                pathType: path.path_type,
                                backendServicePortName: path.backend_service_port_name,
                            }
                        }) ?? [],
                        annotations: ingress.deployment_specification_ingress_annotations?.map((annotation: DeploymentSpecificationIngressAnnotation) => {
                            return {
                                name: annotation.name,
                                value: annotation.value,
                            }
                        }) ?? [],
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
        deploymentSpecification: props.deploymentSpecification,
        items: row.paths?.map(path => {
            const rulePath = new DeploymentSpecificationIngressRulePath();
            rulePath.path = path.path;
            rulePath.path_type = path.pathType;
            rulePath.backend_service_port_name = path.backendServicePortName;
            return rulePath;
        }) ?? [],
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

function onAnnotationsBtnClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdateIngressAnnotations', {
        deploymentSpecification: props.deploymentSpecification,
        items: row.annotations?.map(annotation => {
            const item = new DeploymentSpecificationIngressAnnotation();
            item.name = annotation.name;
            item.value = annotation.value;
            return item;
        }) ?? [],
        onSaveCallback: (items: DeploymentSpecificationIngressAnnotation[]) => {
            row.annotations = items?.map((annotation: DeploymentSpecificationIngressAnnotation) => {
                return {
                    name: annotation.name,
                    value: annotation.value,
                }
            }) ?? [];
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
    const api = Api.deploymentSpecifications()
        .updateIngressesPutById(props.deploymentSpecification.id!);
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
        title="Ingresses"
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
            <template v-slot:item.sslRedirect="{ item }">
                <v-icon v-if="item.sslRedirect">fa fa-check</v-icon>
            </template>
            <template v-slot:item.enableTls="{ item }">
                <v-icon v-if="item.enableTls">fa fa-check</v-icon>
            </template>
            <template v-slot:item.paths="{ item }">
                <span v-if="item.paths">{{ item.paths.length }}</span>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">
                    <v-btn
                        variant="plain" color="primary" size="small"
                        @click="onRulePathsBtnClicked(item)">
                        <v-icon>fa fa-link</v-icon>
                        <v-tooltip activator="parent" location="bottom">Rule paths</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small"
                        @click="onAnnotationsBtnClicked(item)">
                        <v-icon>fa fa-tags</v-icon>
                        <v-tooltip activator="parent" location="bottom">Annotations</v-tooltip>
                    </v-btn>
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
    </page-section>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}
</style>
