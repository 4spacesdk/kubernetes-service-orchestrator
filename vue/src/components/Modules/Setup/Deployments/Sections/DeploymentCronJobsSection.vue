<script setup lang="ts">
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {CopyNameStrategy, duplicateEntity} from "@/helpers/DuplicateEntity";
import {onMounted, ref} from 'vue'
import {Deployment, K8sCronJob} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

interface Row {
    position: number;
    item: K8sCronJob;
}

const props = defineProps<{
    deployment: Deployment
}>();

const isLoading = ref(false);
const itemCount = ref(0);
const rows = ref<Row[]>([]);
const headers = ref([
    {title: '', key: 'handle', sortable: false, width: 30},
    {title: 'Name', key: 'item.name', sortable: false},
    {title: 'Schedule', key: 'item.schedule', sortable: false},
    {title: 'Image', key: 'item.container_image.name', sortable: false},
    {title: '', key: 'actions', sortable: false},
]);
const isSaving = ref(false);

const {markSaved, isChanged} = useUnsavedChanges(() => rows.value);

/**
 * Saves what has changed and answers whether it is stored - for the create wizard, whose Next
 * saves the section it is leaving. The page keeps its Save button: this list is edited row by row.
 */
let settlePending: ((ok: boolean) => void) | null = null;
function settle(ok: boolean) {
    settlePending?.(ok);
    settlePending = null;
}
function saveNow(): Promise<boolean> {
    if (!isChanged()) {
        return Promise.resolve(true);
    }
    if (isSaving.value) {
        // Its Save was pressed and is on its way; that answer is not ours to wait for.
        return Promise.resolve(false);
    }
    return new Promise(resolve => {
        settlePending = resolve;
        onSave();
    });
}
defineExpose({saveNow});

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deployments().get()
        .where('id', props.deployment.id!)
        .include('deployment_cron_job')
        .find(value => {
            rows.value = value[0].deployment_cron_jobs
                ?.sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                ?.map(cronJob => {
                    return {
                        position: cronJob.position ?? 0,
                        item: cronJob.k8s_cron_job!,
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
    bus.emit('cronJobEdit', {
        cronJob: K8sCronJob.CreateDefault(),
        onSaveCallback: (item: K8sCronJob) => {
            Api.k8sCronJobs().getById(item.id!)
                .find(value => rows.value.push({
                    position: rows.value.length,
                    item: value[0]
                }));
        },
    });
}

function onEditRowClicked(row: Row) {
    bus.emit('cronJobEdit', {
        cronJob: row.item,
        onSaveCallback: (item: K8sCronJob) => {
            Api.k8sCronJobs().getById(item.id!)
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

/**
 * A copy of the cron job, opened as a new one: saved, it is a cron job of its own, added here
 * beside the one it came from. Read again first, so fields the list does not show come along.
 * Its name is the container's in the cluster, so the copy's stays a valid one.
 */
function onDuplicateRowClicked(row: Row) {
    Api.k8sCronJobs().getById(row.item.id!).find(items => {
        bus.emit('cronJobEdit', {
            cronJob: duplicateEntity(items[0], K8sCronJob, CopyNameStrategy.Identifier),
            onSaveCallback: (item: K8sCronJob) => {
                Api.k8sCronJobs().getById(item.id!)
                    .find(value => rows.value.push({
                        position: rows.value.length,
                        item: value[0],
                    }));
            },
        });
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
    const api = Api.deployments().updateCronJobsPutById(props.deployment.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        settle(false);
        return false;
    });
    api.save({
            values: rows.value
                .sort((a, b) => a.position - b.position)
                .map(row => row.item.id!)
        },
        newItem => {
            bus.emit('deploymentSaved', newItem);
            bus.emit('toast', {text: 'Saved'});
            isSaving.value = false;
            render();
            settle(true);
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
        title="Cron Jobs"
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

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :items-per-page="-1"
            class="table"
            v-sortableDataTable
            @sorted="onSortChanged"
            density="compact">
            <template v-slot:item.item.name="{ item }">
                <name-link @click="onEditRowClicked(item)">{{ item.item.name }}</name-link>
            </template>
            <template v-slot:item.handle="{ item }">
                <v-icon class="grabbable">fa fa-grip-vertical</v-icon>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end">
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onEditRowClicked(item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>
                    <v-btn
                        variant="plain" color="primary" size="small" icon
                        @click="onDuplicateRowClicked(item)">
                        <v-icon>fa fa-clone</v-icon>
                        <v-tooltip activator="parent" location="bottom">Duplicate</v-tooltip>
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
