<script setup lang="ts">
import {onMounted, ref, watch} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {ReferenceData} from "@/core/referenceData";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/** Which project the workspace is listed under. A project is relevance, not access. */
const props = defineProps<{
    workspace: Workspace
}>();

const value = ref<number>();
const items = ref<{ id: number, name: string }[]>([]);
const isLoadingItems = ref(false);

const {autoSave, markLoaded, saveNow, isChanged} = useAutoSave({
    state: () => value.value,
    request: () => Api.workspaces().updateProjectIdPutById(props.workspace.id!).value(value.value ?? 0),
    onSaved: saved => bus.emit('workspaceSaved', saved),
});
defineExpose({saveNow});

onMounted(() => {
    isLoadingItems.value = true;
    ReferenceData.projects().then(response => {
        items.value = [
            {id: 0, name: 'No project'},
            ...response.map(project => ({id: project.id!, name: project.name ?? ''})),
        ];
        isLoadingItems.value = false;
    });
});

// The page reads the workspace again after a save; the form follows - unless something has
// been typed since, which the read would otherwise undo.
watch(() => props.workspace, () => {
    if (isChanged()) {
        return;
    }
    value.value = props.workspace.project_id ?? 0;
    markLoaded();
}, {immediate: true});

</script>

<template>
    <page-section
        title="Project"
        :auto-save="autoSave">
        <div class="section-form">
            <v-select
                v-model="value"
                :loading="isLoadingItems"
                :items="items"
                item-title="name"
                item-value="id"
                variant="outlined"
                label="Project"/>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
