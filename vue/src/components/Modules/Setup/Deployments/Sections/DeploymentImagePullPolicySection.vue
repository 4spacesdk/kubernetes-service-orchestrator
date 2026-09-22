<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {ImagePullPolicies} from "@/constants";
import {useDialogSave} from "@/composables/useDialogSave";
import {useUnsavedChanges} from "@/composables/useUnsavedChanges";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    deployment: Deployment
}>();

const {isSaving, save} = useDialogSave();

const isLoading = ref(false);
const value = ref<string>();
const imagePullPolicies = ref([
    {
        identifier: ImagePullPolicies.IfNotPresent,
        name: "If not present",
    },
    {
        identifier: ImagePullPolicies.Always,
        name: "Always",
    },
    {
        identifier: ImagePullPolicies.Never,
        name: "Never",
    },
]);

const {markSaved} = useUnsavedChanges(() => value.value);

// <editor-fold desc="Functions">

onMounted(() => {
    render();
});

function render() {
    isLoading.value = true;
    Api.deployments().getById(props.deployment.id!)
        .find(response => {
            value.value = response[0]?.image_pull_policy ?? '';
            isLoading.value = false;
            markSaved();
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSave() {
    const api = Api.deployments().updateImagePullPolicyPutById(props.deployment.id!)
        .value(value.value!)
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
        title="Image Pull Policy"
        :is-loading="isLoading"
        :is-saving="isSaving"
        @save="onSave">
        <div class="section-form">
            <v-select
                v-model="value"
                :items="imagePullPolicies"
                item-title="name"
                item-value="identifier"
                variant="outlined"
                label="Image Pull Policy"/>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
