<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {PodioFieldReference, PodioIntegration} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";

const model = defineModel<PodioFieldReference>();

const props = defineProps<{
    label?: string,
}>();

const integrations = ref<PodioIntegration[]>([]);
const isLoadingIntegrations = ref(false);

const fields = ref<{name?: string, id?: string, type?: string}[]>([]);
const isLoadingFields = ref(false);

watch(() => model.value?.podio_integration_id, loadFields);

onMounted(() => {
    isLoadingIntegrations.value = true;
    Api.podioIntegrations().get()
        .find(values => {
            integrations.value = values;
            isLoadingIntegrations.value = false;
            if (!model.value!.podio_integration_id) {
                model.value!.podio_integration_id = values[0].id!
            }
        });

    loadFields();
});

function loadFields() {
    if (model.value!.podio_integration_id) {
        isLoadingFields.value = true;
        Api.podioIntegrations().getFieldsGetById(model.value!.podio_integration_id!)
            .find(values => {
                fields.value = values;
                isLoadingFields.value = false;
            });
    }
}

</script>

<template>
    <v-card
        class="pa-2"
    >
        <v-row dense>
            <v-col cols="12">
                <h3>{{ props.label ?? 'Podio Field Reference ' }}</h3>
            </v-col>
            <v-col cols="6">
                <v-select
                    v-model="model!.podio_integration_id"
                    :loading="isLoadingIntegrations"
                    :items="integrations"
                    label="Integration"
                    item-title="name"
                    item-value="id"
                    density="compact"
                    hide-details
                    variant="outlined"
                />
            </v-col>
            <v-col cols="6">
                <v-select
                    v-model="model!.field_id"
                    :loading="isLoadingFields"
                    :items="fields"
                    label="Field"
                    item-title="name"
                    item-value="id"
                    density="compact"
                    hide-details
                    variant="outlined"
                >
                    <template v-slot:item="{ props, item }">
                        <v-list-item v-bind="props"
                                     :subtitle="item.raw.type"></v-list-item>
                    </template>
                </v-select>
            </v-col>
        </v-row>
    </v-card>
</template>

<style scoped>

</style>
