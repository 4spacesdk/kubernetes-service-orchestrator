<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {PodioIntegration} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";

const model = defineModel();

const props = defineProps<{
    label?: string,
}>();

const integrations = ref<PodioIntegration[]>([]);
const isLoadingIntegrations = ref(false);

onMounted(() => {
    isLoadingIntegrations.value = true;
    Api.podioIntegrations().get()
        .find(values => {
            integrations.value = values;
            isLoadingIntegrations.value = false;
            if (!model.value) {
                model.value = values[0].id!
            }
        });
});

</script>

<template>
    <v-select
        v-model="model"
        :loading="isLoadingIntegrations"
        :items="integrations"
        :label="props.label ?? 'Integrations'"
        item-title="name"
        item-value="id"
        density="compact"
        hide-details
        variant="outlined"
    />
</template>

<style scoped>

</style>
