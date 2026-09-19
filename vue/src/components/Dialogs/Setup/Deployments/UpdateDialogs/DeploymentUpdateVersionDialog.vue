<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import DateView from "@/components/Modules/Common/DateView.vue";

export interface DeploymentUpdateVersionDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentUpdateVersionDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const value = ref<string>();
const tags = ref<string[]>([]);
/** When each tag was pushed, by name. A tag the registry gives no time for is missing. */
const pushedAt = ref<Record<string, string>>({});
const isLoading = ref(false);
const isSaving = ref(false);

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
    value.value = props.input.deployment.version ?? '';
    showDialog.value = true;

    isLoading.value = true;
    Api.deploymentSpecifications().getTagsGetById(props.input.deployment!.deployment_specification_id!)
        .find(response => {
            pushedAt.value = Object.fromEntries(
                (response[0]?.tag_details ?? [])
                    .filter(tag => tag.pushed_at)
                    .map(tag => [tag.name!, tag.pushed_at!])
            );
            // Last pushed first, like the tags dialog. A tag without a time goes last, highest
            // version first - the server answers oldest version first.
            tags.value = [...(response[0]?.tags ?? [])]
                .reverse()
                .sort((a, b) => (pushedAt.value[b] ?? "").localeCompare(pushedAt.value[a] ?? ""));
            isLoading.value = false;
        });
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    isSaving.value = true;
    const api = Api.deployments().updateVersionPutById(props.input.deployment.id!)
        .value(value.value!)
    api.setErrorHandler(response => {
        if (response.error) {
            bus.emit('toast', {
                text: response.error
            });
        }
        isSaving.value = false;
        return false;
    });
    api.save(null, newItem => {
        bus.emit('deploymentSaved', newItem);
        close();
        isSaving.value = false;
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
                        <v-select
                            v-model="value"
                            :loading="isLoading"
                            :items="tags"
                            variant="outlined"
                            label="Version">
                            <template v-slot:item="{ props: itemProps, item }">
                                <v-list-item v-bind="itemProps">
                                    <template v-if="pushedAt[item.raw]" v-slot:append>
                                        <DateView :date-string="pushedAt[item.raw]" text-format="DD/MM-YY HH:mm" class="text-body-2 text-medium-emphasis ml-4 pushed-at"/>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-select>
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
                    :loading="isSaving"
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
/* Same width for every date, so they line up: Roboto kerns around a 1. */
.pushed-at {
    font-variant-numeric: tabular-nums;
    font-kerning: none;
}
</style>
