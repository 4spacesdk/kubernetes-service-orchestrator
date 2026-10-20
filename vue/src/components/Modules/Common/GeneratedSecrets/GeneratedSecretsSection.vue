<script setup lang="ts">
import {onMounted, ref} from 'vue';
import {Api, type GeneratedSecret, type GeneratedSecretVersion} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import GeneratedSecretsTable from "@/components/Modules/Common/GeneratedSecrets/GeneratedSecretsTable.vue";

/**
 * The secrets kso made for a deployment or a workspace - `${secret.<name>}` and
 * `${workspace.secret.<name>}` in an environment variable. Made the first time a deploy asks,
 * and listed by name: the value is shown only when somebody asks, and that is recorded. Every
 * value one no longer has is kept, and can be shown and made current again.
 */
const props = defineProps<{
    owner: 'deployment' | 'workspace',
    ownerId: number,
}>();

const isLoading = ref(false);
const rows = ref<GeneratedSecret[]>([]);
const table = ref<InstanceType<typeof GeneratedSecretsTable>>();

/** The secret a value is being set for by hand, while the dialog is open. */
const settingFor = ref<GeneratedSecret | null>(null);
const settingValue = ref('');
const isSetting = ref(false);

const api = () => props.owner === 'deployment' ? Api.deployments() : Api.workspaces();
const previousPlaceholder = (name: string) => props.owner === 'deployment' ? `\${secret.${name}.previous}` : `\${workspace.secret.${name}.previous}`;

onMounted(load);

function load() {
    isLoading.value = true;
    api().getSecretsGetById(props.ownerId).find(responses => {
        show(responses[0]?.secrets ?? []);
        isLoading.value = false;
    });
}

function show(secrets: GeneratedSecret[]) {
    table.value?.forget();
    rows.value = secrets;
}

function reveal(name: string, version?: number): Promise<string> {
    return new Promise(resolve => {
        const request = api().revealSecretPutById(props.ownerId).name(name);
        if (version !== undefined) {
            request.version(version);
        }
        request.save(null, response => resolve(response.value ?? ''));
    });
}

function onRotate(row: GeneratedSecret) {
    const details = ['What was signed or opened with the current value stops working - signed links and open realtime connections, for instance.'];
    if (!row.pending) {
        details.push(
            `The current value is kept as version ${row.version}, and can be shown and made current again.`,
            `${previousPlaceholder(row.name!)} gives it from then on - for an app that reads what the old value encrypted while it encrypts again with the new one.`,
        );
    }
    bus.emit('confirm', {
        title: `Rotate ${row.name}?`,
        body: 'The next deploy makes a new value, as the placeholders are written then.',
        details,
        confirmIcon: 'fa fa-rotate',
        confirmColor: 'warning',
        responseCallback: (confirmed: boolean) => {
            if (!confirmed) {
                return;
            }
            api().rotateSecretPutById(props.ownerId).name(row.name!).save(null, response => {
                show(response.secrets ?? rows.value);
                bus.emit('toast', {text: `'${row.name}' rotated - deploy to put it to use`});
            });
        },
    });
}

function onRestore(row: GeneratedSecret, version: GeneratedSecretVersion) {
    bus.emit('confirm', {
        title: `Make version ${version.version} of ${row.name} current again?`,
        body: 'The next deploy puts it to use.',
        details: row.pending ? [] : [`The current value is kept as version ${row.version}.`],
        confirmIcon: 'fa fa-clock-rotate-left',
        confirmColor: 'warning',
        responseCallback: (confirmed: boolean) => {
            if (!confirmed) {
                return;
            }
            api().restoreSecretPutById(props.ownerId).name(row.name!).version(version.version!).save(null, response => {
                show(response.secrets ?? rows.value);
                bus.emit('toast', {text: `'${row.name}' is version ${version.version} again - deploy to put it to use`});
            });
        },
    });
}

function onSet(row: GeneratedSecret) {
    settingFor.value = row;
    settingValue.value = '';
}

function onSetSaveClicked() {
    const row = settingFor.value!;
    isSetting.value = true;
    const request = api().setSecretPutById(props.ownerId).name(row.name!);
    request.setErrorHandler(response => {
        bus.emit('toast', {text: response.error ?? 'failed to set the value'});
        isSetting.value = false;
        return false;
    });
    request.save({value: settingValue.value}, response => {
        show(response.secrets ?? rows.value);
        isSetting.value = false;
        settingFor.value = null;
        settingValue.value = '';
        bus.emit('toast', {text: `'${row.name}' set - deploy to put it to use`});
    });
}
</script>

<template>
    <page-section
        title="Secrets"
        flush
        hide-save
        :is-loading="isLoading">
        <p class="text-medium-emphasis pa-4 mb-0">
            Set an environment variable's value to <strong>Generated secret</strong>, give it a name and say what it is made of,
            and kso makes a random value the first time it deploys - the same in every container that uses the name. Rotating one
            makes it anew at the next deploy. Every value a secret had is kept and can be made current again, and
            <code>.previous</code> after the name - <code>{{ previousPlaceholder('name') }}</code> - is the value before the current one.
        </p>

        <GeneratedSecretsTable
            ref="table"
            :rows="rows"
            :reveal="reveal"
            editable
            no-data-text="None yet - they are made when a deploy first asks for one."
            @rotate="onRotate"
            @restore="onRestore"
            @set="onSet"/>

        <v-dialog :model-value="settingFor !== null" max-width="560" @update:model-value="settingFor = null">
            <v-card v-if="settingFor">
                <v-card-title>Set {{ settingFor.name }} by hand</v-card-title>
                <v-card-text>
                    <p class="text-medium-emphasis mb-4">
                        For a value kept elsewhere - a backup, a password manager - put back. It becomes a new version, the one it
                        replaces is kept, and the next deploy puts it to use. It keeps the recipe
                        <code>{{ settingFor.recipe || settingFor.versions?.[0]?.recipe }}</code>.
                    </p>
                    <v-text-field
                        v-model="settingValue"
                        label="Value"
                        type="password"
                        autocomplete="off"
                        variant="outlined"
                        density="compact"
                        autofocus/>
                </v-card-text>
                <v-card-actions>
                    <v-spacer/>
                    <v-btn variant="text" @click="settingFor = null">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" :disabled="!settingValue" :loading="isSetting" @click="onSetSaveClicked">Set</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </page-section>
</template>
