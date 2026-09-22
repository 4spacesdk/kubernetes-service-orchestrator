<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment, DeploymentVolume} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    deployment: Deployment
}>();

const isLoading = ref(false);
const isSaving = ref(false);
const itemCount = ref(0);
const rows = ref<DeploymentVolume[]>([]);
const headers = ref([
    {title: 'Type', key: 'type', sortable: false},
    {title: 'Capacity', key: 'capacity', sortable: false},
    {title: 'Mount Path', key: 'mount_path', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);

const {markSaved} = useUnsavedChanges(() => rows.value);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deployments().get()
        .where('id', props.deployment.id!)
        .include('deployment_volume')
        .find(value => {
            rows.value = value[0].deployment_volumes ?? [];
            itemCount.value = rows.value.length;
            isLoading.value = false;
            markSaved();
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCreateBtnClicked() {
    const newItem = new DeploymentVolume();
    bus.emit('deploymentUpdateVolume', {
        volume: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: DeploymentVolume) {
    bus.emit('deploymentUpdateVolume', {
        volume: row,
        onSaveCallback: () => {

        }
    });
}

function onDeleteRowClicked(row: DeploymentVolume) {
    rows.value.splice(rows.value.indexOf(row), 1);
}

function onSave() {
    if (isSaving.value) {
        return;
    }
    isSaving.value = true;
    const api = Api.deployments().updateDeploymentVolumesPutById(props.deployment.id!);
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
        bus.emit('deploymentSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
        isSaving.value = false;
        render();
    });
}

// </editor-fold>
</script>

<template>
    <page-section
        title="Volumes"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <template #actions>
            <!-- A wrapper, so the tooltip still shows while the button is disabled. -->
            <span>
                <v-btn
                    icon
                    variant="plain"
                    color="secondary"
                    size="small"
                    :disabled="rows.length >= 1"
                    @click="onCreateBtnClicked()">
                    <v-icon>fa fa-plus</v-icon>
                </v-btn>
                <v-tooltip activator="parent" location="bottom">
                    {{ rows.length >= 1 ? 'One volume per deployment: every volume is named after the deployment' : 'Create' }}
                </v-tooltip>
            </span>
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
