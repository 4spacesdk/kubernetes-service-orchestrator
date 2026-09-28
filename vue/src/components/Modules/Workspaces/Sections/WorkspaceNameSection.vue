<script setup lang="ts">
import {ref, watch} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    workspace: Workspace
}>();

const name = ref<string>();

const {autoSave, markLoaded, saveNow, isChanged} = useAutoSave({
    state: () => name.value,
    validate: () => name.value?.trim() ? null : 'the workspace needs a name',
    request: () => Api.workspaces().updateNamePutById(props.workspace.id!).value(name.value!),
    onSaved: saved => bus.emit('workspaceSaved', saved),
});
defineExpose({saveNow});

// The page reads the workspace again after a save; the form follows - unless something has
// been typed since, which the read would otherwise undo.
watch(() => props.workspace, () => {
    if (isChanged()) {
        return;
    }
    name.value = props.workspace.name_readable;
    markLoaded();
}, {immediate: true});

</script>

<template>
    <page-section
        title="Name"
        :auto-save="autoSave">
        <div class="section-form">
            <v-text-field
                v-model="name"
                variant="outlined"
                label="Name"
                clearable/>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
