<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import moment from "moment";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Api} from "@/core/services/Deploy/Api";
import type {ClusterDomain} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

/**
 * The cluster's cert-manager Certificates held up against kso's domains: which kso has - and what
 * differs - and which it does not, to be taken over as a domain. See `ClusterDomains` in the
 * backend.
 *
 * As with gateways, a takeover: kso's next apply of the certificate replaces its spec. The plan is
 * shown first - above all a secret kso would rename - and the certificate's name has to be typed.
 */
export interface DomainsInClusterDialog_Input {
}

const props = defineProps<{ input: DomainsInClusterDialog_Input, events: DialogEventsInterface }>();

const showDialog = ref(false);
const isLoading = ref(false);
const error = ref<string>();
const rows = ref<ClusterDomain[]>([]);

/** Operators' own certificates for `….svc` names: not domains, and only shown when asked for. */
const showInternal = ref(false);
const internalCount = computed(() => rows.value.filter(row => row.status == 'internal').length);

const looks: Record<string, { title: string, color: string }> = {
    known: {title: 'In kso', color: 'success'},
    unknown: {title: 'Not in kso', color: 'info'},
    internal: {title: 'Inside the cluster', color: 'grey'},
    theirs: {title: "Another kso's", color: 'grey'},
};

const importing = ref<ClusterDomain>();
const confirmation = ref('');
const isImporting = ref(false);

onMounted(() => {
    showDialog.value = true;
    load();
});

function load() {
    isLoading.value = true;
    error.value = undefined;
    const api = Api.domains().getInClusterGet();
    api.setErrorHandler(response => {
        error.value = response.error ?? 'The cluster could not be read';
        isLoading.value = false;
        return false;
    });
    api.find(response => {
        rows.value = response;
        isLoading.value = false;
    });
}

function startImport(row: ClusterDomain) {
    importing.value = row;
    confirmation.value = '';
}

function onImportBtnClicked() {
    const row = importing.value!;
    isImporting.value = true;
    const api = Api.domains().importPost()
        .namespace(row.namespace!)
        .name(row.name!)
        .confirm(confirmation.value);
    api.setErrorHandler(response => {
        bus.emit('toast', {text: response.error ?? 'The certificate could not be taken over'});
        isImporting.value = false;
        return false;
    });
    api.save(null, () => {
        bus.emit('toast', {text: `kso has ${row.plan?.domain} now`});
        bus.emit('domainSaved');
        isImporting.value = false;
        importing.value = undefined;
        load();
    });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}
</script>

<template>
    <v-dialog
        v-model="showDialog"
        max-width="1000"
        scrollable
        @click:outside="close"
        @keydown.esc="close">
        <v-card :loading="isLoading">
            <v-card-title class="d-flex align-center">
                <span>Certificates in the cluster</span>
                <v-spacer/>
                <v-btn
                    variant="text"
                    prepend-icon="fa fa-refresh"
                    :loading="isLoading"
                    @click="load">
                    Reload
                </v-btn>
            </v-card-title>
            <v-divider/>

            <v-card-text>
                <div v-if="error" class="text-error inset">{{ error }}</div>
                <div v-else-if="!isLoading && !rows.length" class="text-medium-emphasis inset">The cluster has no cert-manager Certificates.</div>

                <div
                    v-for="row in rows.filter(r => r.status != 'internal' || showInternal)"
                    :key="`${row.namespace}/${row.name}`"
                    class="certificate"
                    :class="{internal: row.status == 'internal'}">
                    <div class="d-flex align-center flex-wrap ga-2">
                        <strong>{{ row.status == 'known' ? row.dns_names?.find(n => !n.startsWith('*.')) ?? row.name : row.plan?.domain ?? row.name }}</strong>
                        <span class="muted">{{ row.namespace }}/{{ row.name }}</span>
                        <v-chip v-if="row.ready !== null && row.ready !== undefined" size="x-small" label :color="row.ready ? 'success' : 'error'">
                            {{ row.ready ? 'Ready' : 'Not ready' }}
                        </v-chip>
                        <span v-if="row.not_after" class="muted">expires {{ moment(row.not_after).fromNow() }}</span>
                        <v-spacer/>
                        <v-chip size="small" label variant="tonal" :color="looks[row.status!]?.color">
                            {{ looks[row.status!]?.title ?? row.status }}
                        </v-chip>
                        <v-btn
                            v-if="row.status == 'unknown' && !row.plan?.conflict && importing != row"
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="fa fa-file-import"
                            @click="startImport(row)">
                            Take over
                        </v-btn>
                    </div>
                    <div class="muted mt-1">{{ row.dns_names?.join(', ') }} · {{ row.issuer }}</div>

                    <ul v-if="row.differences?.length" class="list text-warning">
                        <li v-for="difference in row.differences" :key="difference">{{ difference }}</li>
                    </ul>
                    <div v-if="row.plan?.conflict" class="muted mt-1">{{ row.plan.conflict }}</div>

                    <div v-if="importing == row" class="plan">
                        <p>
                            kso will own <strong>{{ row.plan?.domain }}</strong> and its certificate: kso's next apply
                            replaces the certificate's spec. Nothing is applied until then.
                        </p>
                        <div v-if="row.plan?.gateway" class="mb-1">Linked to the gateway <strong>{{ row.plan.gateway.name }}</strong>, which listens for it.</div>
                        <div v-else class="mb-1 muted">No kso gateway listens for it; it is on none.</div>

                        <template v-if="row.plan?.changes?.length">
                            <div class="heading text-error">Changed by the next apply</div>
                            <ul class="list"><li v-for="change in row.plan.changes" :key="change">{{ change }}</li></ul>
                        </template>
                        <div v-else class="muted mb-2">The next apply leaves the certificate as it is.</div>

                        <div class="d-flex align-center ga-2 mt-2">
                            <v-text-field
                                v-model="confirmation"
                                density="compact"
                                variant="outlined"
                                hide-details
                                :placeholder="`Type ${row.name} to take it over`"
                                class="confirm"/>
                            <v-btn
                                color="error"
                                variant="tonal"
                                :disabled="confirmation !== row.name"
                                :loading="isImporting"
                                @click="onImportBtnClicked">
                                Take over
                            </v-btn>
                            <v-btn variant="text" @click="importing = undefined">Cancel</v-btn>
                        </div>
                    </div>
                </div>
                <div v-if="internalCount" class="inset mt-3">
                    <v-btn size="small" variant="text" @click="showInternal = !showInternal">
                        {{ showInternal ? 'Hide' : 'Show' }} {{ internalCount }} for names inside the cluster
                    </v-btn>
                </div>
            </v-card-text>

            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="close">
                    Close
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.inset {
    padding: 0 16px;
}

.certificate {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.internal {
    opacity: 0.6;
}

.muted {
    font-size: 12px;
    opacity: 0.6;
}

.list {
    margin: 6px 0 6px 20px;
    font-size: 12px;
}

.plan {
    margin-top: 10px;
    padding: 12px;
    border-radius: 8px;
    background: rgba(var(--v-theme-on-surface), 0.04);
    font-size: 13px;
}

.heading {
    margin-top: 8px;
    font-size: 12px;
    font-weight: 600;
}

.confirm {
    max-width: 320px;
}
</style>
