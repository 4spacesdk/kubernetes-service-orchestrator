<script setup lang="ts">
import { useDialogSave } from "@/composables/useDialogSave";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DatabaseService} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import { secretHint } from "@/helpers/SecretHint";

export interface DatabaseServiceEditDialog_Input {
    databaseService: DatabaseService;
}

const props = defineProps<{input: DatabaseServiceEditDialog_Input, events: DialogEventsInterface}>();

const { isSaving, save } = useDialogSave();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<DatabaseService>(new DatabaseService());
const drivers = ref([
    {
        identifier: 'mysql',
        name: 'MySQL',
    },
    {
        identifier: 'mssql',
        name: 'MSSQL',
    },
]);

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
    if (props.input.databaseService.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.databaseServices().getById(props.input.databaseService.id!).find(items => {
            item.value = items[0];
            isLoading.value = false;
        });
    } else {
        item.value = props.input.databaseService;
        // What the column defaults to, so the switch shows what will be stored.
        item.value.tls_verify ??= true;
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('databaseServiceEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value!.exists() ? Api.databaseServices().patchById(item.value!.id!) : Api.databaseServices().post();

    save(api, item.value!, newItem => {
        bus.emit('databaseServiceSaved', newItem);
        close();
    });
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
            :disabled="isLoading">
            <v-card-title>Database Service</v-card-title>
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
                            v-model="item.driver"
                            :items="drivers"
                            item-value="identifier"
                            item-title="name"
                            variant="outlined"
                            label="Driver"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="item.host"
                            label="Host"/>
                    </v-col>
                    <v-col
                        v-if="item.driver == 'mysql'"
                        cols="6"
                    >
                        <v-text-field
                            variant="outlined"
                            v-model="item.azure_host"
                            label="Azure host"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model.number="item.port"
                            type="number"
                            label="Port"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="item.user"
                            label="Username"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="item.pass"
                            label="Password"
                            persistent-hint
                            :hint="secretHint(item, 'pass')"/>
                    </v-col>

                    <v-col cols="12">
                        <v-switch
                            v-model="item.tls"
                            color="primary"
                            hide-details
                            label="TLS"/>
                    </v-col>
                    <template v-if="item.tls && item.driver == 'mssql'">
                        <v-col cols="12" class="text-body-2 text-medium-emphasis">
                            The server's certificate is checked against the public CAs kso's image trusts, as for Azure SQL.
                        </v-col>
                    </template>
                    <template v-if="item.tls && item.driver == 'mysql'">
                        <v-col cols="12">
                            <v-switch
                                v-model="item.tls_verify"
                                color="primary"
                                persistent-hint
                                hint="Signed by the CA and naming the host. Off, the connection is encrypted but the server is not checked"
                                label="Check the server's certificate"/>
                        </v-col>
                        <v-col cols="12">
                            <v-textarea
                                variant="outlined"
                                v-model="item.tls_ca"
                                rows="3"
                                class="font-monospace"
                                persistent-hint
                                hint="PEM. Without one, the server is not checked"
                                label="CA certificate"/>
                        </v-col>
                        <v-col cols="6">
                            <v-textarea
                                variant="outlined"
                                v-model="item.tls_client_cert"
                                rows="3"
                                class="font-monospace"
                                persistent-hint
                                hint="PEM, for a server that asks for one"
                                label="Client certificate"/>
                        </v-col>
                        <v-col cols="6">
                            <v-textarea
                                variant="outlined"
                                v-model="item.tls_client_key"
                                rows="3"
                                class="font-monospace"
                                persistent-hint
                                :hint="secretHint(item, 'tls_client_key')"
                                label="Client key"/>
                        </v-col>
                    </template>

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
                    color="success"
                    :loading="isSaving"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
