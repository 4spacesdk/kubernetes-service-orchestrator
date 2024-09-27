<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {PodioFieldReference} from "@/core/services/Deploy/models";
import {
    Api,
} from "@/core/services/Deploy/Api";
import {
    type PodioIntegrationGetFieldDetailsResponse,
} from "@/core/services/Deploy/Api";
import {
    type PodioIntegrationGetFieldDetailsResponseOption
} from "@/core/services/Deploy/Api";

const model = defineModel<string>();

const props = defineProps<{
    field: PodioFieldReference,
    label: string,
}>();

const field = ref<PodioIntegrationGetFieldDetailsResponse>();
const isLoadingFieldDetails = ref(false);

const showSelect = ref(false);
const selectOptions = ref<PodioIntegrationGetFieldDetailsResponseOption[]>([]);

const showTextInput = ref(false);

const showNumberInput = ref(false);

watch(props.field, loadFieldDetails);

onMounted(() => {
    loadFieldDetails();
});

function loadFieldDetails() {
    if (props.field.podio_integration_id && props.field.field_id) {
        isLoadingFieldDetails.value = true;
        Api.podioIntegrations().getFieldDetailsGetByIdByFieldId(props.field.podio_integration_id!, props.field.field_id!)
            .find(values => {
                field.value = values[0];
                isLoadingFieldDetails.value = false;

                selectOptions.value = field.value.options ?? [];
                showSelect.value = field.value.type == 'category';

                showTextInput.value = field.value.type == 'text';

                showNumberInput.value = field.value.type == 'progress';

            });
    }
}

</script>

<template>
    <div v-if="field">
        <v-select
            v-if="showSelect"
            v-model="model"
            :label="props.label"
            :items="selectOptions"
            item-title="text"
            item-value="id"
            density="compact"
            hide-details
            variant="outlined"
        />
        <v-text-field
            v-if="showTextInput"
            v-model="model"
            :label="props.label"
            density="compact"
            hide-details
            variant="outlined"
        />
        <v-text-field
            v-if="showNumberInput"
            v-model="model"
            :label="props.label"
            type="number"
            density="compact"
            hide-details
            variant="outlined"
        />
    </div>
</template>

<style scoped>

</style>
