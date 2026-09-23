<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

interface Row {
    name: string;
    command: string;
    allPods: boolean;
    container: string;
    position: number;
}

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: '', key: 'handle', sortable: false, width: 30},
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Container', key: 'container', sortable: false},
    {title: 'Command', key: 'command', sortable: false},
    {title: 'All pods', key: 'allPods', sortable: false},
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
        .include('deployment_specification_post_command')
        .find(value => {
            let pos = 0;
            rows.value = value[0].deployment_specification_post_commands
                ?.map(postCommand => {
                    return {
                        name: postCommand.name ?? '',
                        command: postCommand.command ?? '',
                        allPods: postCommand.all_pods ?? false,
                        container: postCommand.container ?? '',
                        position: pos++,
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
        name: '',
        command: '',
        container: '',
        allPods: false,
        position: rows.value.length - 1,
    };
    bus.emit('deploymentSpecificationUpdatePostCommand', {
        postCommand: newItem,
        onSaveCallback: () => rows.value.push(newItem),
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('deploymentSpecificationUpdatePostCommand', {
        postCommand: row,
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
    const api = Api.deploymentSpecifications().updatePostCommandsPutById(props.deploymentSpecification.id!);
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
        values: [...rows.value].sort((a, b) => a.position - b.position)
    }, newItem => {
        bus.emit('deploymentSpecificationSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
        isSaving.value = false;
        render();
    });
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
        title="Post Migration Commands"
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
            v-sortableDataTable
            @sorted="onSortChanged"
            class="table"
            density="compact">
            <template v-slot:item.handle="{ item }">
                <v-icon class="grabbable">fa fa-grip-vertical</v-icon>
            </template>
            <template v-slot:item.allPods="{ item }">
                <v-icon v-if="item.allPods">fa fa-check</v-icon>
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
.grabbable {
     cursor: move; /* fallback if grab cursor is unsupported */
     cursor: grab;
     cursor: -moz-grab;
     cursor: -webkit-grab;
 }
</style>
