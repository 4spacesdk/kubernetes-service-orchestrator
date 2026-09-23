<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import DateView from "@/components/Modules/Common/DateView.vue";

const props = defineProps<{
    deployment: Deployment
}>();

const value = ref<string>();
const tags = ref<string[]>([]);
/** When each tag was pushed, by name. A tag the registry gives no time for is missing. */
const pushedAt = ref<Record<string, string>>({});
const isLoading = ref(false);
const isLoadingTags = ref(false);
const isSaving = ref(false);

const {markSaved} = useUnsavedChanges(() => value.value);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
    loadTags();
});

function render() {
    isLoading.value = true;
    Api.deployments().getById(props.deployment.id!)
        .find(response => {
            value.value = response[0]?.version ?? '';
            isLoading.value = false;
            markSaved();
        });
}

function loadTags() {
    isLoadingTags.value = true;
    Api.deploymentSpecifications().getTagsGetById(props.deployment.deployment_specification_id!)
        .find(response => {
            pushedAt.value = Object.fromEntries(
                (response[0]?.tag_details ?? [])
                    .filter(tag => tag.pushed_at)
                    .map(tag => [tag.name!, tag.pushed_at!])
            );
            // Last pushed first, like the tags dialog. A tag without a time goes last, highest
            // version first - the server answers oldest version first.
            tags.value = [...(response[0]?.tags ?? [])]
                .reverse()
                .sort((a, b) => (pushedAt.value[b] ?? "").localeCompare(pushedAt.value[a] ?? ""));
            isLoadingTags.value = false;
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSave() {
    if (isSaving.value) {
        return;
    }
    isSaving.value = true;
    const api = Api.deployments().updateVersionPutById(props.deployment.id!)
        .value(value.value!)
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save(null, newItem => {
        bus.emit('deploymentSaved', newItem);
        bus.emit('toast', {text: 'Saved'});
        isSaving.value = false;
        render();
    });
}

// </editor-fold>

</script>

<template>
    <page-section
        title="Version"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <div class="section-form">
            <v-select
                v-model="value"
                :loading="isLoadingTags"
                :items="tags"
                variant="outlined"
                label="Version">
                <template v-slot:item="{ props: itemProps, internalItem: item }">
                    <v-list-item v-bind="itemProps">
                        <template v-if="pushedAt[item.raw]" v-slot:append>
                            <DateView :date-string="pushedAt[item.raw]" text-format="DD/MM-YY HH:mm" class="text-body-medium text-medium-emphasis ml-4 pushed-at"/>
                        </template>
                    </v-list-item>
                </template>
            </v-select>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}

/* Same width for every date, so they line up: Roboto kerns around a 1. */
.pushed-at {
    font-variant-numeric: tabular-nums;
    font-kerning: none;
}
</style>
