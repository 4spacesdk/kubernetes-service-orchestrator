<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {KNativeMinScaleSchedule} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {VTextField} from "vuetify/components/VTextField";

export interface KNativeMinScaleScheduleEditDialog_Input {
    knativeMinScaleSchedule: KNativeMinScaleSchedule;

    onSaveCallback?: (item: KNativeMinScaleSchedule) => void;
}

const props = defineProps<{ input: KNativeMinScaleScheduleEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<KNativeMinScaleSchedule>(new KNativeMinScaleSchedule());

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
    if (props.input.knativeMinScaleSchedule.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.kNativeMinScaleSchedules().getById(props.input.knativeMinScaleSchedule.id!).find(items => {
            if (!items || items.length == 0) {
                return;
            }
            item.value = items[0];
            isLoading.value = false;
        });
    } else {
        item.value = props.input.knativeMinScaleSchedule;
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('knativeMinScaleScheduleEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value!.exists() ? Api.kNativeMinScaleSchedules().patchById(item.value!.id!) : Api.kNativeMinScaleSchedules().post();

    api.save(item.value!, newItem => {
        bus.emit('knativeMinScaleScheduleSaved', newItem);

        if (props.input.onSaveCallback) {
            props.input.onSaveCallback(newItem);
        }
        close();
    });

    close();
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
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading">
            <v-card-title>KNative Min Scale Schedule</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model.number="item.min_scale"
                            label="Min Scale"
                            density="compact"
                            type="number"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.cron_expression"
                            :rules="[
                                v => /^(\*|([0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])|\*\/([0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])) (\*|([0-9]|1[0-9]|2[0-3])|\*\/([0-9]|1[0-9]|2[0-3])) (\*|([1-9]|1[0-9]|2[0-9]|3[0-1])|\*\/([1-9]|1[0-9]|2[0-9]|3[0-1])) (\*|([1-9]|1[0-2])|\*\/([1-9]|1[0-2])) (\*|([0-6])(-([0-6]))?(,([0-6])(-([0-6]))?)*|\*\/([0-6]))$/.test(v) || 'Invalid format'
                            ]"
                            label="Schedule"
                            density="compact"
                            persistent-hint
                            hint="[minutes] [hours] [day-of-month] [month] [day-of-week]"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.timezone"
                            label="Timezone"
                            density="compact"
                            persistent-hint
                            hint="Europe/Copenhagen"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.description"
                            label="Description"
                            density="compact"
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
                    color="green"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
