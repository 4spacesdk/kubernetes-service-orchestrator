<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    deployment: Deployment
}>();

const isLoading = ref(false);
const enabled = ref<boolean>();
const tagRegex = ref<string>();
const requireApproval = ref<boolean>();

const {autoSave, markLoaded, saveNow} = useAutoSave({
    state: () => [enabled.value, tagRegex.value, requireApproval.value],
    validate: () => enabled.value && !tagRegex.value?.trim() ? 'auto update needs a tag pattern' : null,
    request: () => Api.deployments().updateUpdateManagementPutById(props.deployment.id!)
        .enabled(enabled.value!)
        .tagRegex(tagRegex.value!)
        .requireApproval(requireApproval.value!),
    onSaved: saved => bus.emit('deploymentSaved', saved),
});
defineExpose({saveNow});

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
            markLoaded();
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">


// </editor-fold>

</script>

<template>
    <page-section
        title="Update Management"
        :is-loading="isLoading"
        :auto-save="autoSave">
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
