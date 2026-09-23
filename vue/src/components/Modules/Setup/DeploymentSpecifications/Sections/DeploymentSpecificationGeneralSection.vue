<script setup lang="ts">
import {onMounted, onUnmounted, ref, watch} from 'vue'
import {onBeforeRouteLeave, onBeforeRouteUpdate} from "vue-router";
import debounce from "lodash.debounce";
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import DeploymentSpecificationForm from "@/components/Modules/Setup/DeploymentSpecifications/Form/DeploymentSpecificationForm.vue";

/**
 * The specification's own fields, saved as they are changed - a moment after the last one, so
 * typing in a field is one save and not one a key. A change the form's rules turn down is
 * not saved, and says so.
 */
const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

type Status = 'idle' | 'pending' | 'saving' | 'saved' | 'invalid' | 'failed';

const form = ref<InstanceType<typeof DeploymentSpecificationForm> | null>(null);
const isLoading = ref(false);
const item = ref<DeploymentSpecification>();
const status = ref<Status>('idle');
const error = ref('');

/** What the server has, to tell a change from a value set back to what it was. */
let saved = '';
let isSaving = false;
let saveAgain = false;

onMounted(() => {
    load();
});

onUnmounted(() => {
    saveSoon.cancel();
});

function load() {
    isLoading.value = true;
    Api.deploymentSpecifications().getById(props.deploymentSpecification.id!)
        .find(items => {
            item.value = items[0];
            saved = JSON.stringify(items[0]);
            isLoading.value = false;
        });
}

const isChanged = () => !!item.value && JSON.stringify(item.value) != saved;

watch(item, () => {
    if (isChanged()) {
        status.value = 'pending';
        saveSoon();
    } else if (status.value != 'idle' && !isSaving) {
        // Set back to what the server has: nothing is waiting any more.
        saveSoon.cancel();
        status.value = 'saved';
    }
}, {deep: true});

const saveSoon = debounce(() => saveNow(), 600);

async function saveNow() {
    saveSoon.cancel();
    if (isSaving) {
        saveAgain = true;
        return;
    }
    if (!isChanged()) {
        status.value = status.value == 'pending' ? 'saved' : status.value;
        return;
    }
    const {valid} = await form.value!.validate();
    if (!valid) {
        status.value = 'invalid';
        return;
    }
    form.value!.prepareForSave();

    isSaving = true;
    status.value = 'saving';
    const sent = JSON.stringify(item.value);
    const api = Api.deploymentSpecifications().patchById(item.value!.id!);
    api.setErrorHandler(response => {
        isSaving = false;
        status.value = 'failed';
        error.value = String(response?.error ?? response?.message ?? 'Could not save');
        return false;
    });
    api.save(item.value!, result => {
        isSaving = false;
        saved = sent;
        status.value = 'saved';
        bus.emit('deploymentSpecificationSaved', result);
        if (saveAgain) {
            saveAgain = false;
            saveNow();
        }
    });
}

/** Leaving saves what is waiting, unless it cannot be saved - then it asks. */
function beforeLeave(): boolean | Promise<boolean> {
    if (!isChanged()) {
        return true;
    }
    if (status.value != 'invalid' && status.value != 'failed') {
        saveNow();
        return true;
    }
    return new Promise(resolve => bus.emit('confirm', {
        body: 'Your last changes could not be saved. Leave without them?',
        responseCallback: (confirmed: boolean) => resolve(confirmed),
    }));
}

onBeforeRouteLeave(() => beforeLeave());
onBeforeRouteUpdate((to, from) => to.path == from.path || beforeLeave());

</script>

<template>
    <page-section
        title="General"
        :is-loading="isLoading"
        hide-save>
        <template #actions>
            <span
                class="save-status"
                :class="`save-status--${status}`">
                <template v-if="status == 'pending' || status == 'saving'">
                    <v-progress-circular indeterminate size="12" width="2"/>
                    Saving…
                </template>
                <template v-else-if="status == 'saved'">
                    <v-icon size="12">fa fa-check</v-icon>
                    Saved
                </template>
                <template v-else-if="status == 'invalid'">
                    <v-icon size="12">fa fa-circle-exclamation</v-icon>
                    Not saved - check the fields marked in red
                </template>
                <template v-else-if="status == 'failed'">
                    <v-icon size="12">fa fa-circle-exclamation</v-icon>
                    Not saved: {{ error }}
                </template>
                <template v-else>Changes are saved as you make them</template>
            </span>
        </template>

        <deployment-specification-form
            v-if="item"
            ref="form"
            class="general-form"
            :item="item"/>
    </page-section>
</template>

<style scoped>
.general-form {
    max-width: 960px;
}

.save-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: rgba(var(--v-theme-on-background), 0.55);
}

.save-status--saved {
    color: rgb(var(--v-theme-success));
}

.save-status--invalid,
.save-status--failed {
    color: rgb(var(--v-theme-error));
}
</style>
