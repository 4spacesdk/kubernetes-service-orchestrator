<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, Domain} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {DeploymentStatusTypes} from "@/constants";

export interface DeploymentUpdateIngressDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateIngressDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const domainId = ref<number>();
const domains = ref<Domain[]>([]);
const isLoadingDomains = ref(false);
const subdomain = ref<string>();
const aliases = ref<string>();
const isDomainEnabled = ref(false);
const isSubdomainEnabled = ref(false);
const isAliasEnabled = ref(false);

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
    domainId.value = props.input.deployment.domain_id;
    subdomain.value = props.input.deployment.subdomain ?? '';
    aliases.value = props.input.deployment.aliases ?? '';
    isDomainEnabled.value = props.input.deployment.status === DeploymentStatusTypes.Draft;
    isSubdomainEnabled.value = props.input.deployment.status === DeploymentStatusTypes.Draft;
    isAliasEnabled.value = true;
    showDialog.value = true;
    isLoadingDomains.value = true;

    Api.domains().get()
        .find(items => {
            domains.value = items;
            isLoadingDomains.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = Api.deployments().updateIngressPutById(props.input.deployment.id!)
        .domainId(domainId.value!)
        .subdomain(subdomain.value!)
        .aliases(aliases.value!)
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        return false;
    });
    api.save(null, newItem => {
        bus.emit('deploymentSaved', newItem);
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
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>Deployment</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-tooltip
                            :disabled="isDomainEnabled"
                            location="bottom">
                            <template v-slot:activator="{ props }">
                                <div
                                    v-bind="props">
                                    <v-select
                                        v-model="domainId"
                                        :disabled="!isDomainEnabled"
                                        :loading="isLoadingDomains"
                                        :items="domains"
                                        item-title="name"
                                        item-value="id"
                                        variant="outlined"
                                        label="Domains"/>
                                </div>
                            </template>
                            Only available during setup
                        </v-tooltip>
                    </v-col>
                    <v-col cols="12">
                        <v-tooltip
                            :disabled="isSubdomainEnabled"
                            location="bottom">
                            <template v-slot:activator="{ props }">
                                <div
                                    v-bind="props">
                                    <v-text-field
                                        v-model="subdomain"
                                        :disabled="!isSubdomainEnabled"
                                        variant="outlined"
                                        label="Subdomain"
                                        :rules="[
                                            v => v.length === 0 || /[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?/.test(v) || 'Invalid format'
                                        ]"
                                        persistent-hint
                                        hint="Max 63 characters, must begin with an alpha-numeric"/>
                                </div>
                            </template>
                            Only available during setup
                        </v-tooltip>
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            v-model="aliases"
                            :disabled="!isAliasEnabled"
                            variant="outlined"
                            label="Aliases"
                            persistent-hint
                            clearable
                            hint="Comma-seperated: deploy,deployment,server"/>
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
