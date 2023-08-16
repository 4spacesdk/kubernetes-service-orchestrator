<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Webhook, User} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface WebhookEditDialog_Input {
    webhook: Webhook;
}

const props = defineProps<{ input: WebhookEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<Webhook>(new Webhook());

const isLoadingTypes = ref(false);
const typeItems = ref<string[]>([]);

const contentTypeItems = ref([
    'application/json',
    'application/x-www-form-urlencoded',
]);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    if (props.input.webhook.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.webhooks().getById(props.input.webhook.id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
                render();
            });
    } else {
        item.value = props.input.webhook;
        showDialog.value = true;
        render();
    }
});

onUnmounted(() => {
});

function render() {
    isLoadingTypes.value = true;
    Api.webhooks().typesGetGet()
        .find(types => {
            typeItems.value = types.map(item => item.name ?? '');
            isLoadingTypes.value = false;
        });
}

function close() {
    showDialog.value = false;
    bus.emit('webhookEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value.exists()
        ? Api.webhooks().patchById(item.value!.id!)
        : Api.webhooks().post();

    api.save(item.value!, newItem => {
        bus.emit('webhookSaved', newItem);
        close();
    });

    close();
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        height="60vh"
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading"
        >
            <v-card-title>Webhook</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            label="Name"/>
                    </v-col>
                    <v-col cols="12">
                        <v-select
                            variant="outlined"
                            v-model="item.type"
                            label="Type"
                            :items="typeItems"
                            :loading="isLoadingTypes"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.url"
                            label="Url"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-select
                            variant="outlined"
                            v-model="item.content_type"
                            label="Content Type"
                            :items="contentTypeItems"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.auth_bearer_token"
                            label="Auth bearer token"
                        />
                    </v-col>
                </v-row>
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

                <v-btn
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="green"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
