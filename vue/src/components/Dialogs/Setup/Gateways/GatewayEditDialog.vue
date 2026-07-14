<script setup lang="ts">
import {onMounted, onUnmounted, ref} from 'vue'
import {Gateway, GatewayAddress} from "@/core/services/Deploy/models";
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

const addressTypePattern = /^(Hostname|IPAddress|NamedAddress|([a-z0-9]+(\.[a-z0-9]+)*\/)?[A-Za-z0-9\/\-._~%!$&'()+,;=:]+)$/;
const addressTypes = ['Hostname', 'IPAddress', 'NamedAddress', 'Custom'];

function onTypeUpdate(address: any, newValue: string) {
    if (newValue === 'Custom') {
        address.type = '';
    }
}

function addAddress() {
    if (!item.value.gateway_addresses) {
        item.value.gateway_addresses = [];
    }
    item.value.gateway_addresses.push(new GatewayAddress());
}

function removeAddress(index: number) {
    item.value.gateway_addresses?.splice(index, 1);
}

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
        Api.gateways().getById(props.input.gateway.id!)
            .find(items => {
                item.value = items[0];
                if (!item.value.gateway_addresses) {
                    item.value.gateway_addresses = [];
                }
                isLoading.value = false;
            });
    } else {
        item.value = props.input.gateway;
        if (!item.value.gateway_addresses) {
            item.value.gateway_addresses = [];
        }
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('gatewayEditDialog_closed', item.value);
    props.events.onClose();
}

function onSaveBtnClicked() {
    isLoading.value = true;

    const saveAddresses = (gateway: Gateway) => {
        Api.gateways().updateGatewayAddressesPutById(gateway.id!)
            .save({values: item.value.gateway_addresses || []}, () => {
                bus.emit('gatewaySaved', gateway);
                isLoading.value = false;
                close();
            });
    }

    if (item.value.exists()) {
        Api.gateways().patchById(item.value.id!)
            .save(item.value!, newItem => {
                saveAddresses(newItem);
            });
    } else {
        Api.gateways().post()
            .save(item.value!, newItem => {
                saveAddresses(newItem);
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
                    <v-col cols="12" class="mt-4">
                        <div class="d-flex align-center">
                            <div class="text-subtitle-1">Addresses</div>
                            <v-spacer/>
                            <v-btn
                                variant="tonal"
                                size="small"
                                prepend-icon="fa fa-plus"
                                @click="addAddress">
                                Add Address
                            </v-btn>
                        </div>
                        <v-divider class="my-2"/>
                        <v-row v-for="(address, index) in item.gateway_addresses" :key="index" dense>
                            <v-col cols="5">
                                <v-combobox
                                    variant="outlined"
                                    v-model="address.type"
                                    label="Type"
                                    :items="addressTypes"
                                    placeholder="eg. IPAddress"
                                    @update:model-value="v => onTypeUpdate(address, v)"
                                    hint="Select a type or enter a custom one (format: example.com/type-name)"
                                    persistent-hint
                                    :rules="[v => !!v || 'Required', v => v.length <= 253 || 'Too long', v => addressTypePattern.test(v) || 'Invalid type']"
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    variant="outlined"
                                    v-model="address.value"
                                    label="Value"
                                    placeholder="eg. 1.2.3.4"
                                    hide-details="auto"
                                    :rules="[v => !!v || 'Required', v => v.length <= 253 || 'Too long']"
                                />
                            </v-col>
                            <v-col cols="1" class="d-flex mt-1">
                                <v-btn
                                    icon="fa fa-trash"
                                    color="red"
                                    variant="text"
                                    size="x-small"
                                    @click="removeAddress(index)"/>
                            </v-col>
                        </v-row>
                        <div v-if="!item.gateway_addresses || item.gateway_addresses.length === 0" class="text-center text-grey text-caption py-4">
                            No addresses defined.
                        </div>
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
