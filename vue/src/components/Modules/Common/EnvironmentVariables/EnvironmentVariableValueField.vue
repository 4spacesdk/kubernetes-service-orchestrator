<script setup lang="ts">
import {computed, ref, watch} from "vue";
import {
    DefaultSecret,
    type GeneratedSecretSettings,
    looksSecret,
    parseGeneratedSecret,
    SecretKinds,
    secretNameFor,
    takesAPassword,
    writeGeneratedSecret,
} from "@/components/Modules/Common/EnvironmentVariables/environmentVariables";

/**
 * The value of an environment variable, and whether it is secret - or a secret kso makes.
 *
 * **Text:** a secret one is typed hidden, and one already stored is never shown - the server does
 * not send it - so the field is left empty to keep it. A name that looks like a secret gets a
 * suggestion to mark it. A value that takes a password from kso is secret either way, but is not
 * hidden here: what is written is the placeholder, not the password.
 *
 * **Generated secret:** a name, whose it is and what it is made of, set with fields. What is saved
 * is the placeholder the server reads (`${secret.name | randAlphaNum 32}`); nobody writes one. The
 * same name in another variable - a sidecar's - is the same value.
 *
 * The slot `append` is for what sits beside the text field, such as the button that inserts a
 * `${…}` placeholder.
 */
const props = defineProps<{
    name: string;
    /** A secret value is stored for this variable already. */
    hasStoredSecret?: boolean;
    density?: 'default' | 'comfortable' | 'compact';
}>();

const value = defineModel<string>({required: true});
const secret = defineModel<boolean>('secret', {required: true});

const showValue = ref(false);

const generated = ref<GeneratedSecretSettings | null>(parseGeneratedSecret(value.value));
const mode = ref<'text' | 'generated'>(generated.value ? 'generated' : 'text');
/** The secret's name follows the variable's until somebody changes it. */
const nameFollows = ref(!generated.value || generated.value.name === secretNameFor(props.name));

const isSecretEitherWay = computed(() => takesAPassword(value.value));
const suggestSecret = computed(() => !secret.value && !isSecretEitherWay.value && looksSecret(props.name));
const staysSecret = computed(() => !secret.value && props.hasStoredSecret && !value.value);
const hidden = computed(() => secret.value && !showValue.value);
const kind = computed(() => SecretKinds.find(kind => kind.value === generated.value?.kind));
const nameIsValid = computed(() => /^[a-z0-9_]+$/.test(generated.value?.name ?? ''));

function onModeChanged(next: 'text' | 'generated') {
    if (next === 'generated') {
        generated.value ??= {owner: 'deployment', name: secretNameFor(props.name), ...DefaultSecret};
        // Secret either way on the server - and left unmarked, the placeholder stays readable here.
        secret.value = false;
        write();
    } else if (parseGeneratedSecret(value.value)) {
        value.value = '';
    }
}

function write() {
    if (generated.value && mode.value === 'generated') {
        value.value = writeGeneratedSecret(generated.value);
    }
}

watch(() => props.name, name => {
    if (generated.value && nameFollows.value) {
        generated.value.name = secretNameFor(name);
        write();
    }
});

watch(generated, write, {deep: true});
watch(mode, onModeChanged);

// The dialogs set the value once they are mounted, after this field was made - and a placeholder
// pasted into an empty field is one too. Typing one out by hand does not switch under the cursor.
watch(value, (next, previous) => {
    if (mode.value !== 'text' || previous) {
        return;
    }
    const settings = parseGeneratedSecret(next);
    if (settings) {
        generated.value = settings;
        nameFollows.value = settings.name === secretNameFor(props.name);
        mode.value = 'generated';
    }
});
</script>

<template>
    <div>
        <v-btn-toggle
            v-model="mode"
            mandatory
            density="compact"
            variant="outlined"
            color="primary"
            divided
            class="mb-3">
            <v-btn value="text" size="small">Text</v-btn>
            <v-btn value="generated" size="small">Generated secret</v-btn>
        </v-btn-toggle>

        <template v-if="mode === 'generated' && generated">
            <v-row density="compact">
                <v-col cols="12" sm="6">
                    <v-text-field
                        v-model="generated.name"
                        variant="outlined"
                        label="Secret name"
                        spellcheck="false"
                        :density="props.density"
                        :error-messages="nameIsValid ? [] : ['a-z, 0-9 and _']"
                        hint="The same name in another variable is the same value"
                        persistent-hint
                        @update:model-value="nameFollows = false"/>
                </v-col>
                <v-col cols="12" sm="6">
                    <v-select
                        v-model="generated.owner"
                        :items="[{value: 'deployment', title: 'The deployment'}, {value: 'workspace', title: 'The workspace - all its deployments'}]"
                        variant="outlined"
                        label="Shared by"
                        :density="props.density"
                        hide-details/>
                </v-col>
                <v-col cols="12" sm="6">
                    <v-select
                        v-model="generated.kind"
                        :items="SecretKinds"
                        variant="outlined"
                        label="Made of"
                        :density="props.density"
                        hide-details/>
                </v-col>
                <v-col v-if="kind?.hasLength" cols="12" sm="6">
                    <v-text-field
                        v-model.number="generated.length"
                        type="number"
                        min="1"
                        max="4096"
                        variant="outlined"
                        label="Length"
                        :density="props.density"
                        hide-details/>
                </v-col>
            </v-row>
            <div class="text-body-small text-medium-emphasis mt-2">
                kso makes it the first time it deploys and keeps it - see Secrets on the deployment or workspace, where it can be rotated.
                Secret either way: it reaches the pod through a Kubernetes Secret.
            </div>
        </template>

        <template v-else>
            <div class="d-flex">
                <v-text-field
                    v-model="value"
                    variant="outlined"
                    label="Value"
                    spellcheck="false"
                    autocomplete="new-password"
                    :type="hidden ? 'password' : 'text'"
                    :density="props.density"
                    :placeholder="props.hasStoredSecret ? 'Stored - leave empty to keep it' : undefined"
                    :persistent-placeholder="props.hasStoredSecret"
                    :append-inner-icon="secret ? (showValue ? 'fa fa-eye-slash' : 'fa fa-eye') : undefined"
                    @click:append-inner="showValue = !showValue"
                    hide-details
                />
                <slot name="append"/>
            </div>

            <v-switch
                :model-value="secret || isSecretEitherWay"
                @update:model-value="secret = !!$event"
                :disabled="isSecretEitherWay"
                color="secondary"
                density="compact"
                label="Secret"
                hide-details
                class="mt-2"
            />
            <div class="text-body-small text-medium-emphasis">
                <template v-if="isSecretEitherWay">
                    Takes a password or a secret from kso, so it is secret either way.
                </template>
                <template v-else-if="secret">
                    Write-only: the value is never shown again, and reaches the pod through a Kubernetes Secret.
                </template>
                <template v-else-if="staysSecret">
                    Stays secret until you enter a new value.
                </template>
                <template v-else>
                    Shown here and in the pod spec.
                </template>
            </div>
            <v-alert
                v-if="suggestSecret"
                density="compact"
                variant="tonal"
                type="info"
                class="mt-2"
            >
                <div class="d-flex align-center ga-2">
                    <span>The name looks like a secret.</span>
                    <v-btn size="small" variant="text" color="primary" @click="secret = true">Mark as secret</v-btn>
                    <v-btn size="small" variant="text" color="primary" @click="mode = 'generated'">Let kso make it</v-btn>
                </div>
            </v-alert>
        </template>
    </div>
</template>
