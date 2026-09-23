<script setup lang="ts">
import {onMounted, ref, watch} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {ReferenceData} from "@/core/referenceData";
import bus from "@/plugins/bus";
import {useDialogSave} from "@/composables/useDialogSave";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    workspace: Workspace
}>();

const {isSaving, save} = useDialogSave();

const value = ref<number>();
const items = ref<{ id: number, name: string }[]>([]);
const isLoadingItems = ref(false);

const {markSaved} = useUnsavedChanges(() => value.value);

onMounted(() => {
    isLoadingItems.value = true;
    ReferenceData.emailServices().then(response => {
        items.value = response.map(item => ({id: item.id!, name: item.name ?? ''}));
        isLoadingItems.value = false;
    });
});

// The page reads the workspace again after a save; the form follows.
watch(() => props.workspace, () => {
    value.value = props.workspace.email_service_id;
    markSaved();
}, {immediate: true});

function onSave() {
    const api = Api.workspaces().updateEmailServiceIdPutById(props.workspace.id!)
        .value(value.value!);
    save(api, null, newItem => {
        bus.emit('workspaceSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
    });
}
</script>

<template>
    <page-section
        title="Email Service"
        :is-saving="isSaving"
        @save="onSave">
        <div class="section-form">
            <v-select
                v-model="value"
                :loading="isLoadingItems"
                :items="items"
                item-title="name"
                item-value="id"
                variant="outlined"
                label="Email Service"/>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
