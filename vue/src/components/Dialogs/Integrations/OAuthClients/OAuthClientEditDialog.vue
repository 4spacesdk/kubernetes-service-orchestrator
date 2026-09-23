<script setup lang="ts">
import { useDialogSave } from "@/composables/useDialogSave";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {OAuthClient, User} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import { secretHint } from "@/helpers/SecretHint";

export interface OAuthClientEditDialog_Input {
    oAuthClient: OAuthClient;
}

const props = defineProps<{ input: OAuthClientEditDialog_Input, events: DialogEventsInterface }>();

const { isSaving, save } = useDialogSave();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const isEditing = ref(false);
const item = ref<OAuthClient>(new OAuthClient());
const grantTypeItems = ref([
    {
        name: 'Client Credentials',
        value: 'client_credentials',
    },
    {
        name: 'Authorization Code',
        value: 'authorization_code',
    },
    {
        name: 'Refresh Token',
        value: 'refresh_token',
    },
    {
        name: 'Implicit',
        value: 'implicit',
    },
]);
const grantType = ref<string[]>([]);

/**
 * The secret is not handed back once it is saved, so a new client's is the one thing this
 * dialog cannot tell you later. Whoever creates the client picks it, which is why
 * withholding it costs nothing - as long as they know to keep it.
 */
function clientSecretHint(): string {
    return isEditing.value
        ? secretHint(item.value, "client_secret")
        : "Cannot be read back once saved. Keep a copy.";
}

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    if (props.input.oAuthClient.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.oAuthClients().getGetById(props.input.oAuthClient.client_id!)
            .find(items => {
                item.value = items[0];
                isLoading.value = false;
                render();
            });
    } else {
        item.value = props.input.oAuthClient;
        showDialog.value = true;
        render();
    }
});

onUnmounted(() => {
});

function render() {
    isEditing.value = item.value.exists();

    if (item.value.grant_types?.length) {
        grantType.value = item.value.grant_types?.split(' ') ?? [];
    }
    if (!item.value.user) {
        item.value.user = new User();
    }
}

function close() {
    showDialog.value = false;
    bus.emit('oauthClientEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    item.value.grant_types = grantType.value.join(' ');

    const api = isEditing.value
        ? Api.oAuthClients().patchPatchById(item.value!.client_id!)
        : Api.oAuthClients().post();

    save(api, item.value!, newItem => {
        bus.emit('oauthClientSaved', newItem);
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
            :disabled="isLoading"
        >
            <v-card-title>OAuth Client</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    v-if="item.user" density="compact">
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.user.first_name"
                            label="Name"/>
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.client_id"
                            label="Client ID"/>
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.client_secret"
                            label="Client Secret"
                            persistent-hint
                            :hint="clientSecretHint()"/>
                    </v-col>
                    <v-col cols="12">
                        <v-select
                            variant="outlined"
                            v-model="grantType"
                            label="Grant Types"
                            :items="grantTypeItems"
                            item-title="name"
                            item-value="value"
                            multiple
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
