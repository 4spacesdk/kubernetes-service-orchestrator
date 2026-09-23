<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useDialogSave} from "@/composables/useDialogSave";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    deployment: Deployment
}>();

const {isSaving, save} = useDialogSave();

const isLoading = ref(false);
const enabled = ref<boolean>();
const tagRegex = ref<string>();
const requireApproval = ref<boolean>();

const {markSaved} = useUnsavedChanges(() => [enabled.value, tagRegex.value, requireApproval.value]);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deployments().getById(props.deployment.id!)
        .find(response => {
            const deployment = response[0];
            enabled.value = deployment?.auto_update_enabled ?? false;
            tagRegex.value = deployment?.auto_update_tag_regex ?? '';
            requireApproval.value = deployment?.auto_update_require_approval ?? false;
            isLoading.value = false;
            markSaved();
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSave() {
    const api = Api.deployments().updateUpdateManagementPutById(props.deployment.id!)
        .enabled(enabled.value!)
        .tagRegex(tagRegex.value!)
        .requireApproval(requireApproval.value!);
    save(api, null, newItem => {
        bus.emit('deploymentSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
        render();
    });
}

// </editor-fold>

</script>

<template>
    <page-section
        title="Update Management"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <div class="section-form">
            <v-row density="compact">
                <v-col cols="6">
                    <v-checkbox
                        v-model="enabled"
                        density="compact"
                        label="Enabled"/>
                </v-col>
                <v-col cols="6">
                    <v-text-field
                        v-model="tagRegex"
                        variant="outlined"
                        label="Tag regex"/>
                </v-col>
                <v-col cols="6">
                    <v-checkbox
                        v-model="requireApproval"
                        density="compact"
                        label="Require approval"/>
                </v-col>
            </v-row>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
