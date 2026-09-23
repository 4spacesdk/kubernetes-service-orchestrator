<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {DeploymentSpecification, PostUpdateAction} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import PostUpdateActionEditButton
    from "@/components/Modules/Setup/PostUpdateActions/EditButton/PostUpdateActionEditButton.vue";

interface Row {
    position: number;
    item: PostUpdateAction;
}

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: '', key: 'handle', sortable: false, width: 30},
    {title: 'Name', key: 'item.name', sortable: false},
    {title: 'Type', key: 'item.type', sortable: false},
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
        .include('deployment_specification_post_update_action')
        .find(value => {
            rows.value = value[0].deployment_specification_post_update_actions
                ?.sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                ?.map(relation => {
                    return {
                        position: relation.position ?? 0,
                        item: relation.post_update_action!,
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
    bus.emit('postUpdateActionEdit', {
        postUpdateAction: PostUpdateAction.CreateDefault(),
        onSaveCallback: (item: PostUpdateAction) => {
            Api.postUpdateActions().getById(item.id!)
                .find(value => rows.value.push({
                    position: rows.value.length,
                    item: value[0]
                }));
        },
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('postUpdateActionEdit', {
        postUpdateAction: row.item,
        onSaveCallback: (item: PostUpdateAction) => {
            Api.postUpdateActions().getById(item.id!)
                .find(value => {
                    const index = rows.value.indexOf(row);
                    rows.value.splice(index, 1, {
                        position: row.position,
                        item: value[0]
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
    const api = Api.deploymentSpecifications().updatePostUpdateActionsPutById(props.deploymentSpecification.id!);
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
                .sort((a, b) => a.position - b.position)
                .map(row => row.item.id!)
        },
        newItem => {
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
        title="Post Update Actions"
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
            v-sortableDataTable
            @sorted="onSortChanged"
            density="compact">

            <template v-slot:top>
                <span class="px-2 font-weight-light" style="font-size: small;">
                    These actions relate to the task/issue that triggered the auto update.
                    <br>
                    The task/issue is found in the commit message. See container image for settings.
                </span>
            </template>

            <template v-slot:item.handle="{ item }">
                <v-icon class="grabbable">fa fa-grip-vertical</v-icon>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">
                    <v-menu
                        min-width="250">
                        <template v-slot:activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="plain" color="primary" size="small" :icon="true"
                            >
                                <v-icon>fa fa-cog</v-icon>
                                <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                            </v-btn>
                        </template>
                        <post-update-action-edit-button
                            :post-update-action="item.item"/>
                    </v-menu>
                    <v-btn
                        variant="plain" color="primary" size="small" :icon="true"
                        @click="onEditRowClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain"
                        color="error" size="small" :icon="true"
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
