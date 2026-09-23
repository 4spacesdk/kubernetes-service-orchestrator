<script setup lang="ts">
import {computed, ref} from "vue";
import {looksSecret, takesAPassword} from "@/components/Modules/Common/EnvironmentVariables/environmentVariables";

/**
 * The value of an environment variable, and whether it is secret.
 *
 * A secret one is typed hidden, and one already stored is never shown - the server does not
 * send it - so the field is left empty to keep it. A name that looks like a secret gets a
 * suggestion to mark it. A value that takes a password from kso is secret either way, but is
 * not hidden here: what is written is the placeholder, not the password.
 *
 * The slot `append` is for what sits beside the field, such as the button that inserts a
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

const isSecretEitherWay = computed(() => takesAPassword(value.value));
const suggestSecret = computed(() => !secret.value && !isSecretEitherWay.value && looksSecret(props.name));
const staysSecret = computed(() => !secret.value && props.hasStoredSecret && !value.value);
const hidden = computed(() => secret.value && !showValue.value);
</script>

<template>
    <div>
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
                Takes a password from kso, so it is secret either way.
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
            </div>
        </v-alert>
    </div>
</template>
