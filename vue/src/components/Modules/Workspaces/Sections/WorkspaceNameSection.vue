<script setup lang="ts">
import {ref, watch} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useDialogSave} from "@/composables/useDialogSave";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    workspace: Workspace
}>();

const {isSaving, save} = useDialogSave();

const name = ref<string>();

const {markSaved} = useUnsavedChanges(() => name.value);

// The page reads the workspace again after a save; the form follows.
watch(() => props.workspace, () => {
    name.value = props.workspace.name_readable;
    markSaved();
}, {immediate: true});

function onSave() {
    const api = Api.workspaces().updateNamePutById(props.workspace.id!)
        .value(name.value!);
    save(api, null, newItem => {
        bus.emit('workspaceSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
    });
}
</script>

<template>
    <page-section
        title="Name"
        :is-saving="isSaving"
        @save="onSave">
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
