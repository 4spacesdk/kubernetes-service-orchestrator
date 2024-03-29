<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DatabaseService} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface DatabaseServiceEditDialog_Input {
    databaseService: DatabaseService;
}

const props = defineProps<{input: DatabaseServiceEditDialog_Input, events: DialogEventsInterface}>();

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

    api.save(item.value!, newItem => {
        bus.emit('databaseServiceSaved', newItem);
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
                            label="Password"/>
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
