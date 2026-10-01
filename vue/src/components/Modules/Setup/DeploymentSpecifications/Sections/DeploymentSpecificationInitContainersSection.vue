<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {DeploymentSpecification, InitContainer} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import InitContainerEditButton from "@/components/Modules/Setup/InitContainers/EditButton/InitContainerEditButton.vue";

interface Row {
    position: number;
    includeInMigrationJob: boolean;
    item: InitContainer;
}

/**
 * The specification's init containers, or its sidecars - one kind at a time, each with its own
 * list and its own save, so saving one leaves the other alone. Sidecars start first, before every
 * init container, so the order is only within the kind.
 */
const props = defineProps<{
    deploymentSpecification: DeploymentSpecification,
    sidecars?: boolean,
}>();

const isLoading = ref(false);
const isSaving = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: '', key: 'handle', sortable: false, width: 30},
    {title: 'Name', key: 'item.name', sortable: false},
    {title: 'Image', key: 'item.container_image.name', sortable: false},
    {title: 'Include in Migration Job', key: 'includeInMigrationJob', sortable: false},
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
        .include('deployment_specification_init_container')
        .find(value => {
            rows.value = value[0].deployment_specification_init_containers
                ?.filter(initContainer => !!initContainer.init_container?.is_sidecar === !!props.sidecars)
                ?.sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                ?.map(initContainer => {
                    return {
                        position: initContainer.position ?? 0,
                        item: initContainer.init_container!,
                        includeInMigrationJob: initContainer.include_in_migration_job ?? false,
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
    bus.emit('initContainerEdit', {
        initContainer: InitContainer.CreateDefault(props.sidecars),
        onSaveCallback: (item: InitContainer) => {
            Api.initContainers().getById(item.id!)
                .find(value => rows.value.push({
                    position: rows.value.length,
                    item: value[0],
                    includeInMigrationJob: false,
                }));
        },
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('initContainerEdit', {
        initContainer: row.item,
        onSaveCallback: (item: InitContainer) => {
            Api.initContainers().getById(item.id!)
                .find(value => {
                    const index = rows.value.indexOf(row);
                    rows.value.splice(index, 1, {
                        position: row.position,
                        item: value[0],
                        includeInMigrationJob: row.includeInMigrationJob,
                    });
                });
        },
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
    const api = props.sidecars
        ? Api.deploymentSpecifications().updateSidecarsPutById(props.deploymentSpecification.id!)
        : Api.deploymentSpecifications().updateInitContainersPutById(props.deploymentSpecification.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save(
        {
            values: rows.value.map(row => ({
                initContainerId: row.item.id!,
                position: row.position ?? 0,
                includeInMigrationJob: row.includeInMigrationJob
            }))
        },
        newItem => {
            bus.emit('deploymentSpecificationSaved', newItem);
            bus.emit('toast', {text: 'Saved'});
            isSaving.value = false;
            render();
        }
    );
}

function onSortChanged(event: CustomEvent) {
    const oldIndex = event.detail.oldIndex;
    const newIndex = event.detail.newIndex;

    const copy = [...rows.value].sort((a, b) => a.position - b.position);
    const movedItem = copy.splice(oldIndex, 1)[0];
    copy.splice(newIndex, 0, movedItem);

    let pos = 0;
    copy.forEach(item => item.position = pos++);
}

// </editor-fold>
</script>

<template>
    <page-section
        :title="props.sidecars ? 'Sidecars' : 'Init Containers'"
        flush
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

        <p v-if="props.sidecars" class="text-medium-emphasis pa-4 mb-0">
            Containers that keep running beside the app for as long as the pod lives - a push server, a database proxy. They start before the init containers, which can use them. A migration job that includes one still completes.
        </p>

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :items-per-page="-1"
            class="table"
            v-sortableDataTable
            @sorted="onSortChanged"
            density="compact">
            <template v-slot:item.handle="{ item }">
                <v-icon class="grabbable">fa fa-grip-vertical</v-icon>
            </template>
            <template v-slot:item.includeInMigrationJob="{ item }">
                <v-checkbox
                    v-model="item.includeInMigrationJob"
                    density="compact"
                    hide-details
                />
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
                        <init-container-edit-button
                            :init-container="item.item"/>
                    </v-menu>
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onEditRowClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain"
                        color="error" size="small" icon
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

.grabbable {
    cursor: move; /* fallback if grab cursor is unsupported */
    cursor: grab;
    cursor: -moz-grab;
    cursor: -webkit-grab;
}
</style>
