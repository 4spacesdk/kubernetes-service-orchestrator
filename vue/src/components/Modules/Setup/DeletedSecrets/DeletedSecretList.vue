<script setup lang="ts">
import {onMounted, ref} from 'vue';
import moment from "moment";
import {Api, type DeletedSecretOwner} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import GeneratedSecretsTable from "@/components/Modules/Common/GeneratedSecrets/GeneratedSecretsTable.vue";

/**
 * The generated secrets of deployments and workspaces that were deleted, by who they belonged to.
 * Kept because a key a customer's data was encrypted with may be needed again - shown on request,
 * which is recorded, and gone only when somebody purges them, typing the owner's name to do it.
 */
const isLoading = ref(false);
const owners = ref<DeletedSecretOwner[]>([]);

/** The owner being purged, while the dialog is open, and the name typed to confirm it. */
const purging = ref<DeletedSecretOwner | null>(null);
const typedName = ref('');
const isPurging = ref(false);

onMounted(load);

function load() {
    isLoading.value = true;
    Api.deletedSecrets().getDeletedGet().find(responses => {
        owners.value = responses[0]?.owners ?? [];
        isLoading.value = false;
    });
}

function when(time?: string) {
    return time ? moment(time).format('D/M-YY HH:mm') : '';
}

function revealOf(owner: DeletedSecretOwner) {
    return (name: string, version?: number): Promise<string> => new Promise(resolve => {
        const request = Api.deletedSecrets().revealPut().owner(owner.owner!).ownerId(owner.owner_id!).name(name);
        if (version !== undefined) {
            request.version(version);
        }
        request.save(null, response => resolve(response.value ?? ''));
    });
}

function onPurgeClicked(owner: DeletedSecretOwner) {
    purging.value = owner;
    typedName.value = '';
}

function onPurgeConfirmed() {
    const owner = purging.value!;
    isPurging.value = true;
    Api.deletedSecrets().purgePut().owner(owner.owner!).ownerId(owner.owner_id!).save(null, response => {
        owners.value = response.owners ?? [];
        isPurging.value = false;
        purging.value = null;
        bus.emit('toast', {text: `The secrets of ${owner.owner_name} are purged`});
    });
}
</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar density="compact" flat color="toolbar" dark>
            <v-toolbar-title>Deleted Secrets</v-toolbar-title>
            <v-spacer/>
            <v-btn variant="text" prepend-icon="fa fa-refresh" @click="load">Reload</v-btn>
        </v-toolbar>

        <v-progress-linear v-if="isLoading" indeterminate/>

        <div class="pa-4">
            <p class="text-medium-emphasis mb-4">
                A deleted deployment or workspace leaves the secrets kso made for it here, with every version of them - a key a
                customer's data was encrypted with may be needed again. Showing a value is recorded. Purging removes them for good.
            </p>

            <p v-if="!isLoading && !owners.length" class="text-medium-emphasis">
                None - no deployment or workspace with generated secrets has been deleted.
            </p>

            <v-card
                v-for="owner in owners"
                :key="`${owner.owner}-${owner.owner_id}`"
                variant="outlined"
                class="mb-4">
                <div class="d-flex align-center ga-2 pa-4">
                    <v-chip size="small" variant="tonal">{{ owner.owner }}</v-chip>
                    <div>
                        <div class="text-title-medium">
                            {{ owner.owner_name }}
                            <span class="text-medium-emphasis text-body-medium ml-1">{{ owner.owner_namespace }}</span>
                        </div>
                        <div class="text-medium-emphasis text-body-small">Deleted {{ when(owner.deleted) }}</div>
                    </div>
                    <v-spacer/>
                    <v-btn variant="text" color="error" prepend-icon="fa fa-trash" @click="onPurgeClicked(owner)">Purge</v-btn>
                </div>
                <GeneratedSecretsTable
                    :rows="owner.secrets ?? []"
                    :reveal="revealOf(owner)"
                    :editable="false"
                    no-data-text="None"/>
            </v-card>
        </div>

        <v-dialog :model-value="purging !== null" max-width="560" @update:model-value="purging = null">
            <v-card v-if="purging">
                <v-card-title>Purge the secrets of {{ purging.owner_name }}?</v-card-title>
                <v-card-text>
                    <p class="mb-4">
                        {{ (purging.secrets ?? []).map(secret => secret.name).join(', ') }} and every version of them are removed for
                        good. Anything encrypted with one of them can no longer be read, unless the value is kept somewhere else.
                    </p>
                    <v-text-field
                        v-model="typedName"
                        :label="`Type ${purging.owner_name} to purge`"
                        autocomplete="off"
                        variant="outlined"
                        density="compact"
                        autofocus/>
                </v-card-text>
                <v-card-actions>
                    <v-spacer/>
                    <v-btn variant="text" @click="purging = null">Cancel</v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        :disabled="typedName !== purging.owner_name"
                        :loading="isPurging"
                        @click="onPurgeConfirmed">
                        Purge
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
