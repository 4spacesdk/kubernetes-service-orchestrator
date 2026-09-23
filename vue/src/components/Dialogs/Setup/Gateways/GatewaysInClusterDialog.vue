<script setup lang="ts">
import {onMounted, ref} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Api} from "@/core/services/Deploy/Api";
import type {ClusterGateway} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

/**
 * The cluster's Gateways held up against kso's: which kso has - and what differs - and which it
 * does not, to be taken over. See `ClusterGateways` in the backend.
 *
 * Taking one over is a takeover: kso owns it afterwards and may terminate it, and its next Deploy
 * replaces the listeners. So the plan is shown first and the name has to be typed; the import
 * writes kso's rows and applies nothing.
 */
export interface GatewaysInClusterDialog_Input {
}

const props = defineProps<{ input: GatewaysInClusterDialog_Input, events: DialogEventsInterface }>();

const showDialog = ref(false);
const isLoading = ref(false);
const error = ref<string>();
const rows = ref<ClusterGateway[]>([]);

/** The row being taken over, and what has been typed to confirm it. */
const importing = ref<ClusterGateway>();
const confirmation = ref('');
const isImporting = ref(false);

const looks: Record<string, { title: string, color: string }> = {
    known: {title: 'In kso', color: 'success'},
    unknown: {title: 'Not in kso', color: 'info'},
    orphan: {title: "kso's, deleted in kso", color: 'warning'},
};

onMounted(() => {
    showDialog.value = true;
    load();
});

function load() {
    isLoading.value = true;
    error.value = undefined;
    const api = Api.gateways().getInClusterGet();
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

function startImport(row: ClusterGateway) {
    importing.value = row;
    confirmation.value = '';
}

function onImportBtnClicked() {
    const row = importing.value!;
    isImporting.value = true;
    const api = Api.gateways().importPost()
        .namespace(row.namespace!)
        .name(row.name!)
        .confirm(confirmation.value);
    api.setErrorHandler(response => {
        bus.emit('toast', {text: response.error ?? 'The Gateway could not be taken over'});
        isImporting.value = false;
        return false;
    });
    api.save(null, () => {
        bus.emit('toast', {text: `kso has ${row.name} now - check the preview before deploying it`});
        bus.emit('gatewaySaved');
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
                <span>Gateways in the cluster</span>
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
                <div v-else-if="!isLoading && !rows.length" class="text-medium-emphasis inset">The cluster has no Gateways.</div>

                <div
                    v-for="row in rows"
                    :key="`${row.namespace}/${row.name}`"
                    class="gateway">
                    <div class="d-flex align-center flex-wrap ga-2">
                        <strong>{{ row.name }}</strong>
                        <span class="muted">{{ row.namespace }}</span>
                        <v-chip size="x-small" label>{{ row.gateway_class_name }}</v-chip>
                        <span class="muted">{{ row.listeners?.length ?? 0 }} listeners</span>
                        <v-spacer/>
                        <v-chip size="small" label variant="tonal" :color="looks[row.status!]?.color">
                            {{ looks[row.status!]?.title ?? row.status }}
                        </v-chip>
                        <v-btn
                            v-if="row.status != 'known' && importing != row"
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="fa fa-file-import"
                            @click="startImport(row)">
                            Take over
                        </v-btn>
                    </div>

                    <ul v-if="row.differences?.length" class="list text-warning">
                        <li v-for="difference in row.differences" :key="difference">{{ difference }}</li>
                    </ul>

                    <div v-if="importing == row" class="plan">
                        <p>
                            kso will own <strong>{{ row.name }}</strong>: it can be deployed and terminated from kso,
                            and the next Deploy replaces its listeners with kso's. Nothing is applied until then.
                        </p>

                        <template v-if="row.plan?.domains?.length">
                            <div class="heading">Domains linked to it</div>
                            <ul class="list"><li v-for="domain in row.plan.domains" :key="domain.id">{{ domain.name }}</li></ul>
                        </template>
                        <template v-if="row.plan?.domains_elsewhere?.length">
                            <div class="heading">Domains left on another gateway</div>
                            <ul class="list">
                                <li v-for="domain in row.plan.domains_elsewhere" :key="domain.id">{{ domain.name }} - on {{ domain.gateway }}</li>
                            </ul>
                        </template>
                        <template v-if="row.plan?.listeners_removed?.length">
                            <div class="heading text-error">Removed by the next Deploy</div>
                            <ul class="list"><li v-for="listener in row.plan.listeners_removed" :key="listener">{{ listener }}</li></ul>
                        </template>
                        <template v-if="row.plan?.listeners_added?.length">
                            <div class="heading">Added by the next Deploy</div>
                            <ul class="list"><li v-for="listener in row.plan.listeners_added" :key="listener">{{ listener }}</li></ul>
                        </template>
                        <div v-if="!row.plan?.listeners_removed?.length && !row.plan?.listeners_added?.length" class="muted mb-2">
                            The next Deploy leaves the listeners as they are.
                        </div>

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

.gateway {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
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
