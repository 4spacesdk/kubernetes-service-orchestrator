<script setup lang="ts">
import {onMounted, ref, watch} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {ReferenceData} from "@/core/referenceData";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    workspace: Workspace
}>();

const value = ref<number>();
const items = ref<{ id: number, name: string }[]>([]);
const isLoadingItems = ref(false);

const {autoSave, markLoaded, saveNow, isChanged} = useAutoSave({
    state: () => value.value,
    request: () => Api.workspaces().updateDatabaseServiceIdPutById(props.workspace.id!).value(value.value!),
    onSaved: saved => bus.emit('workspaceSaved', saved),
});
defineExpose({saveNow});

onMounted(() => {
    isLoadingItems.value = true;
    ReferenceData.databaseServices().then(response => {
        items.value = response.map(item => ({id: item.id!, name: item.name ?? ''}));
        isLoadingItems.value = false;
    });
});

// The page reads the workspace again after a save; the form follows - unless something has
// been chosen since, which the read would otherwise undo.
watch(() => props.workspace, () => {
    if (isChanged()) {
        return;
    }
    value.value = props.workspace.database_service_id;
    markLoaded();
}, {immediate: true});

</script>

<template>
    <page-section
        title="Database Service"
        :auto-save="autoSave">
        <div class="section-form">
            <v-select
                v-model="value"
                :loading="isLoadingItems"
                :items="items"
                item-title="name"
                item-value="id"
                variant="outlined"
                label="Database Service"/>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
