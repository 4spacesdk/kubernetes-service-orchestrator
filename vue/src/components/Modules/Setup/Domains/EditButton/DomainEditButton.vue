<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {Domain} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

const props = defineProps<{
    domain: Domain
}>();

const isLoading = ref(false);

const showApplyCertificate = ref(false);
const isApplyCertificateEnabled = ref(false);

const showApplyIstioGateway = ref(false);
const isApplyIstioGatewayEnabled = ref(false);

const showTerminateIstioGateway = ref(false);
const isTerminateIstioGatewayEnabled = ref(false);

const showCertificateEvents = ref(false);
const isCertificateEventsEnabled = ref(false);

const showCertificateStatus = ref(false);
const isCertificateStatusEnabled = ref(false);

onMounted(() => {
    render();
});

function render() {
    showApplyCertificate.value = true;
    isApplyCertificateEnabled.value = true;

    showApplyIstioGateway.value = true;
    isApplyIstioGatewayEnabled.value = props.domain.enable_istio_gateway ?? false;

    showTerminateIstioGateway.value = true;
    isTerminateIstioGatewayEnabled.value = props.domain.enable_istio_gateway ?? false;

    showCertificateEvents.value = true;
    isCertificateEventsEnabled.value = true;

    showCertificateStatus.value = true;
    isCertificateStatusEnabled.value = true;
}

function onApplyCertificateClicked() {
    const api = Api.domains().applyCertificatePutById(props.domain.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('json', {
                title: `Failed to apply istio gateway for ${props.domain.name}`,
                body: JSON.parse(response.error),
            });
        }
        return false;
    });
    api.save(() => {

    }, () => {
    });
}

function onApplyIstioGatewayClicked() {
    const api = Api.domains().applyIstioGatewayPutById(props.domain.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('json', {
                title: `Failed to apply istio gateway for ${props.domain.name}`,
                body: JSON.parse(response.error),
            });
        }
        return false;
    });
    api.save(() => {

    }, () => {
        bus.emit('toast', {
            text: 'Istio Gateway applied',
        });
    });
}

function onTerminateIstioGatewayClicked() {
    const api = Api.domains().terminateIstioGatewayPutById(props.domain.id!);
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('json', {
                title: `Failed to terminate istio gateway for ${props.domain.name}`,
                body: JSON.parse(response.error),
            });
        }
        return false;
    });
    api.save(() => {

    }, () => {
    });
}

function onCertificateEventsClicked() {
    Api.domains().getCertificateEventsGetById(props.domain.id!)
        .find(events => {
            bus.emit('info', {
                title: props.domain.name,
                body: events.length
                    ? events.map(event => `${event.age}: ${event.message}`).join('<br>')
                    : 'No events found',
            });
        });
}

function onCertificateStatusClicked() {
    Api.domains().getCertificateStatusGetById(props.domain.id!)
        .find(response => {
            if (response.length == 1 && response[0].conditions?.length) {
                const status = response[0]!;
                const lines = [
                    `Renewal: ${status.renewalTime}`,
                    `Not before: ${status.notBefore}`,
                    `Not after: ${status.notAfter}`,
                    '',
                    '<strong>Conditions</strong>'
                ];
                lines.push(...status.conditions!
                    .map(condition => {
                        return `${condition.lastTransitionTime}: ${condition.type}, ${condition.reason}, ${condition.message}`;
                    })
                );
                bus.emit('info', {
                    title: props.domain.name,
                    body: lines.join('<br>')
                });
            } else {
                bus.emit('info', {
                    title: props.domain.name,
                    body: 'Certificate not found',
                });
            }
        });
}

</script>

<template>
    <div
        class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">

            <v-progress-linear v-if="isLoading"
                               color="primary"
                               indeterminate></v-progress-linear>
            <v-list
                v-if="!isLoading"
                class="list-items">
                <v-list-item
                    v-if="showApplyCertificate"
                    :disabled="!isApplyCertificateEnabled"
                    dense
                    @click="onApplyCertificateClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-certificate</v-icon>
                        <span class="ml-2">Apply Certificate</span>
                    </v-list-item-title>
                </v-list-item>
                <v-tooltip
                    v-if="showApplyIstioGateway"
                    :disabled="isApplyIstioGatewayEnabled"
                    location="left">
                    <template v-slot:activator="{ props }">
                        <div
                            v-bind="props">
                            <v-list-item
                                :disabled="!isApplyIstioGatewayEnabled"
                                dense
                                @click="onApplyIstioGatewayClicked">
                                <v-list-item-title>
                                    <v-icon size="small" class="my-auto ml-2">fa fa-network-wired</v-icon>
                                    <span class="ml-2">Apply Istio Gateway</span>
                                </v-list-item-title>
                            </v-list-item>
                            <v-divider/>
                        </div>
                    </template>
                    Not enabled for this domain
                </v-tooltip>
                <v-tooltip
                    v-if="showTerminateIstioGateway"
                    :disabled="isTerminateIstioGatewayEnabled"
                    location="left">
                    <template v-slot:activator="{ props }">
                        <div
                            v-bind="props">
                            <v-list-item
                                :disabled="!isTerminateIstioGatewayEnabled"
                                dense
                                @click="onTerminateIstioGatewayClicked">
                                <v-list-item-title>
                                    <v-icon size="small" class="my-auto ml-2">fa fa-network-wired</v-icon>
                                    <span class="ml-2">Terminate Istio Gateway</span>
                                </v-list-item-title>
                            </v-list-item>
                            <v-divider/>
                        </div>
                    </template>
                    Not enabled for this domain
                </v-tooltip>
                <v-list-item
                    v-if="showCertificateEvents"
                    :disabled="!isCertificateEventsEnabled"
                    dense
                    @click="onCertificateEventsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-triangle-exclamation</v-icon>
                        <span class="ml-2">Certificate Events</span>
                    </v-list-item-title>
                </v-list-item>
                <v-list-item
                    v-if="showCertificateStatus"
                    :disabled="!isCertificateStatusEnabled"
                    dense
                    @click="onCertificateStatusClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-list-check</v-icon>
                        <span class="ml-2">Certificate Status</span>
                    </v-list-item-title>
                </v-list-item>
            </v-list>
        </v-card>
    </div>
</template>

<style scoped>
.list-wrapper {
    min-width: 120px;
}

.v-list-item {
    min-height: unset;
}

.v-list-item-title {
    font-size: 11px !important;
}

.v-progress-circular {
    margin: 1rem;
}
</style>
