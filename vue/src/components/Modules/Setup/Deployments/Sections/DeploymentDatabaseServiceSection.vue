<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {ReferenceData} from "@/core/referenceData";
import {DatabaseService, Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

const props = defineProps<{
    deployment: Deployment
}>();

const value = ref<number>();
const items = ref<DatabaseService[]>([]);
const isLoading = ref(false);
const isLoadingItems = ref(false);

const {autoSave, markLoaded, saveNow} = useAutoSave({
    state: () => value.value,
    request: () => Api.deployments().updateDatabaseServiceIdPutById(props.deployment.id!).value(value.value!),
    onSaved: saved => bus.emit('deploymentSaved', saved),
});
defineExpose({saveNow});

// <editor-fold desc="Functions">

onMounted(() => {
    render();

    isLoadingItems.value = true;
    ReferenceData.databaseServices().then(response => {
        items.value = response;
        isLoadingItems.value = false;
    });
});

function render() {
    isLoading.value = true;
    Api.deployments().getById(props.deployment.id!)
        .find(response => {
            value.value = response[0]?.database_service_id;
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
        title="Database Service"
        :is-loading="isLoading"
        :auto-save="autoSave">
        <div class="section-form">
            <v-select
                v-model="value"
                :loading="isLoadingItems"
                :items="items"
                item-title="name"
                item-value="id"
                variant="outlined"
                label="Database Service"/>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
