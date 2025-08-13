<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Domain, System} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DomainEditDialog_Input {
    domain: Domain;
}

const props = defineProps<{ input: DomainEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<Domain>(new Domain());
const showIstio = ref(false);
const showContour = ref(false);

// <editor-fold desc="Functions">

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
    showIstio.value = System.Instance.is_network_istio_supported ?? false;
    showContour.value = System.Instance.is_network_contour_supported ?? false;

    if (props.input.domain.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.domains().getById(props.input.domain.id!).find(items => {
            item.value = items[0];
            isLoading.value = false;
        });
    } else {
        item.value = props.input.domain;
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('domainEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    Api.domains().patchById(item.value.id!)
        .save(item.value!, newItem => {
            bus.emit('domainSaved', newItem);
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
            class="w-100 h-100">
            <v-card-title>Domain</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            label="Name"
                            disabled
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.certificate_name"
                            label="Certificate Name"
                            disabled
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.certificate_namespace"
                            label="Certificate Namespace"
                            disabled
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.issuer_ref_name"
                            label="Cert manager issuer name"
                            disabled
                        />
                    </v-col>
                    <v-col cols="12">
                        <v-row dense>
                            <v-col
                                cols="6"
                            >
                                <v-switch
                                    v-model="item.has_certificate_monitoring"
                                    variant="outlined"
                                    label="Enable Certificate Monitoring"
                                    density="compact" hide-details
                                    color="secondary"
                                />
                            </v-col>
                            <v-col
                                v-if="item.has_certificate_monitoring"
                                cols="6"
                            >
                                <v-text-field
                                    variant="outlined"
                                    v-model.number="item.certificate_monitoring_days_before_expiry"
                                    label="Threshold (days before expiration)"
                                    type="number"
                                />
                            </v-col>
                        </v-row>
                    </v-col>
                    <v-col
                        v-if="showIstio"
                        cols="6"
                    >
                        <v-switch
                            v-model="item.enable_istio_gateway"
                            variant="outlined"
                            label="Enable Istio Gateway"
                            density="compact"
                            color="secondary"
                        />
                    </v-col>
                    <v-col
                        v-if="showContour"
                        cols="6"
                    >
                        <v-switch
                            v-model="item.enable_contour"
                            variant="outlined"
                            label="Enable Contour"
                            density="compact"
                            color="secondary"
                        />
                    </v-col>
                    <v-col
                        v-if="showContour && item.enable_contour"
                        cols="6"
                    >
                        <v-text-field
                            variant="outlined"
                            v-model="item.contour_ingress_class_name"
                            label="Contour ingress class name"
                            hint="Default: contour-external"
                            persistent-hint
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
