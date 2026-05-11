<script setup lang="ts">
import {onMounted, onUnmounted, ref} from 'vue'
import {Gateway} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface GatewayEditDialog_Input {
    gateway: Gateway;
}

const props = defineProps<{ input: GatewayEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<Gateway>(new Gateway());

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

onUnmounted(() => {
});

function render() {
    if (props.input.gateway.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.gateways().getById(props.input.gateway.id!).find(items => {
            item.value = items[0];
            isLoading.value = false;
        });
    } else {
        item.value = props.input.gateway;
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('gatewayEditDialog_closed', item.value);
    props.events.onClose();
}

function onSaveBtnClicked() {
    if (item.value.exists()) {
        Api.gateways().patchById(item.value.id!)
            .save(item.value!, newItem => {
                bus.emit('gatewaySaved', newItem);
                close();
            });
    } else {
        Api.gateways().post()
            .save(item.value!, newItem => {
                bus.emit('gatewaySaved', newItem);
                close();
            });
    }
}

function onCloseBtnClicked() {
    close();
}

</script>

<template>
    <v-dialog
        persistent
        width="40vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>Gateway</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            label="Name"
                            :loading="isLoading"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.gateway_class_name"
                            label="Gateway Class Name"
                            placeholder="eg"
                            hint="The name of the GatewayClass that should manage this Gateway (e.g. 'envoy')"
                            persistent-hint
                            :loading="isLoading"
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.namespace"
                            label="Namespace"
                            placeholder="envoy-gateway"
                            hint="The namespace where the Gateway resource should be deployed"
                            persistent-hint
                            :loading="isLoading"
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
