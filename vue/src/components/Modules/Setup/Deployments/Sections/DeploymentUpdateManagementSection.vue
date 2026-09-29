<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    deployment: Deployment
    /**
     * On or off is to be chosen, not left as it is - the create wizard, where leaving it alone
     * is how auto update ends up forgotten.
     */
    requireChoice?: boolean
}>();

const isLoading = ref(false);
const enabled = ref<boolean>();
const tagRegex = ref<string>();
const requireApproval = ref<boolean>();

const {autoSave, markLoaded, saveNow} = useAutoSave({
    state: () => [enabled.value, tagRegex.value, requireApproval.value],
    validate: () => enabled.value === undefined
        ? 'choose whether it updates by itself'
        : (enabled.value && !tagRegex.value?.trim() ? 'auto update needs a tag pattern' : null),
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
            enabled.value = props.requireChoice ? undefined : (deployment?.auto_update_enabled ?? false);
            tagRegex.value = deployment?.auto_update_tag_regex ?? '';
            requireApproval.value = deployment?.auto_update_require_approval ?? false;
            isLoading.value = false;
            markLoaded();
        });
}

// </editor-fold>

/**
 * A pattern to start from, read off the version being deployed: any semver for a semver, and
 * the tag itself for a moving one such as `develop`. Only offered - a guess that looks right is
 * worse than an empty field, so it is set by a click, where it can be seen.
 */
const suggestedPattern = computed(() => {
    const version = props.deployment.version ?? '';
    if (!version) {
        return null;
    }
    if (/^v?\d+\.\d+\.\d+$/.test(version)) {
        return version.startsWith('v') ? 'v\\d+\\.\\d+\\.\\d+' : '\\d+\\.\\d+\\.\\d+';
    }
    return version.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
});

// <editor-fold desc="View Binding Functions">

function onUseSuggestionClicked() {
    tagRegex.value = suggestedPattern.value ?? '';
}

// </editor-fold>

</script>

<template>
    <page-section
        title="Update Management"
        :is-loading="isLoading"
        :auto-save="autoSave">
        <div class="section-form">
            <v-row density="compact">
                <v-col v-if="props.requireChoice" cols="12">
                    <v-radio-group v-model="enabled" hide-details>
                        <v-radio :value="false" label="Update it by hand - a new version is deployed when someone chooses it"/>
                        <v-radio :value="true" label="Update it by itself - a pushed tag that matches the pattern is deployed"/>
                    </v-radio-group>
                </v-col>
                <v-col v-else cols="6">
                    <v-checkbox
                        v-model="enabled"
                        density="compact"
                        label="Enabled"/>
                </v-col>
                <template v-if="!props.requireChoice || enabled">
                    <v-col cols="6">
                        <v-text-field
                            v-model="tagRegex"
                            variant="outlined"
                            label="Tag regex"
                            persistent-hint
                            hint="Matched against the end of a tag"/>
                        <v-chip
                            v-if="props.requireChoice && suggestedPattern && tagRegex !== suggestedPattern"
                            size="small"
                            class="mt-2"
                            prepend-icon="fa fa-wand-magic-sparkles"
                            @click="onUseSuggestionClicked">
                            Use {{ suggestedPattern }}
                        </v-chip>
                    </v-col>
                    <v-col cols="6">
                        <v-checkbox
                            v-model="requireApproval"
                            density="compact"
                            label="Require approval"/>
                    </v-col>
                </template>
            </v-row>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
