<script setup lang="ts">
import {onMounted, ref, watch} from 'vue'
import {Domain, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {ReferenceData} from "@/core/referenceData";
import bus from "@/plugins/bus";
import {useDialogSave} from "@/composables/useDialogSave";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/** Where the workspace answers: its domain, the subdomain on it, and aliases that redirect. */
const props = defineProps<{
    workspace: Workspace
}>();

const {isSaving, save} = useDialogSave();

const domainId = ref<number>();
const subdomain = ref<string>('');
const aliases = ref<string>('');
const domains = ref<Domain[]>([]);
const isLoadingDomains = ref(false);

const {markSaved} = useUnsavedChanges(() => [domainId.value, subdomain.value, aliases.value]);

onMounted(() => {
    isLoadingDomains.value = true;
    ReferenceData.domains().then(items => {
        domains.value = items;
        isLoadingDomains.value = false;
    });
});

// The page reads the workspace again after a save; the form follows.
watch(() => props.workspace, () => {
    domainId.value = props.workspace.domain_id;
    subdomain.value = props.workspace.subdomain ?? '';
    aliases.value = props.workspace.aliases ?? '';
    markSaved();
}, {immediate: true});

function onSave() {
    const api = Api.workspaces().updateIngressPutById(props.workspace.id!)
        .domainId(domainId.value!)
        .subdomain(subdomain.value)
        .aliases(aliases.value);
    save(api, null, newItem => {
        bus.emit('workspaceSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
    });
}
</script>

<template>
    <page-section
        title="Domain"
        :is-saving="isSaving"
        @save="onSave">
        <div class="section-form">
            <v-select
                v-model="domainId"
                :loading="isLoadingDomains"
                :items="domains"
                item-title="name"
                item-value="id"
                variant="outlined"
                label="Domain"/>
            <v-text-field
                v-model="subdomain"
                variant="outlined"
                label="Subdomain"
                :rules="[
                    (v: string) => v.length === 0 || /[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?/.test(v) || 'Invalid format',
                ]"
                persistent-hint
                hint="Max 63 characters, must begin with an alpha-numeric"
                class="mb-4"/>
            <v-text-field
                v-model="aliases"
                variant="outlined"
                label="Aliases"
                persistent-hint
                hint="Comma-separated subdomains or hostnames on the domain: 4spaces.dk,kso. Redirects (301) to the workspace hostname. Gateway API only"/>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
