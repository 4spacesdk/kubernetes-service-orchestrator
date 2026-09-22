<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DeploymentAnnotationLevels} from "@/constants";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

interface Row {
    level: string;
    name: string;
    value: string;
}

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const isLoading = ref(false);
const isSaving = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: 'Level', key: 'level', sortable: false},
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Value', key: 'value', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);

const {markSaved} = useUnsavedChanges(() => rows.value);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deploymentSpecifications().get()
        .where('id', props.deploymentSpecification.id!)
        .include('deployment_specification_deployment_annotation')
        .find(value => {
            rows.value = value[0].deployment_specification_deployment_annotations
                ?.map(deploymentAnnotation => {
                    return {
                        level: deploymentAnnotation.level ?? '',
                        name: deploymentAnnotation.name ?? '',
                        value: deploymentAnnotation.value ?? '',
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
        level: DeploymentAnnotationLevels.Deployment,
        name: '',
        value: '',
    };
    bus.emit('deploymentSpecificationUpdateDeploymentAnnotation', {
        deploymentAnnotation: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdateDeploymentAnnotation', {
        deploymentAnnotation: row,
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
    const api = Api.deploymentSpecifications().updateDeploymentAnnotationsPutById(props.deploymentSpecification.id!);
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
        title="Deployment Annotations"
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
            <template v-slot:item.value="{ item }">
                <span
                    style="max-width: 300px;">{{ item.value }}</span>
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
    </page-section>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}
</style>
