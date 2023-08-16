<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Webhook} from "@/core/services/Deploy/models";
import WebhookDeliveryList from "@/components/Modules/Integrations/Webhooks/Deliveries/List/WebhookDeliveryList.vue";

export interface WebhookDeliveryListDialog_Input {
    webhook?: Webhook;
}

const props = defineProps<{input: WebhookDeliveryListDialog_Input, events: DialogEventsInterface}>();

const used = ref(false);
const showDialog = ref(false);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;
});

onUnmounted(() => {
});

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        height="60vh"
        width="80vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>Deliveries</v-card-title>
            <v-divider/>
            <v-card-text>
                <WebhookDeliveryList
                    :filter-by-webhook-id="props.input.webhook?.id"
                    :show-header="false"
                />
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Close
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
