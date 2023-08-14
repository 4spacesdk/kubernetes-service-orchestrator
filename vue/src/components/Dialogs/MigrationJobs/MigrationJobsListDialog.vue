<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Deployment, Workspace} from "@/core/services/Deploy/models";
import MigrationJobList from "@/components/Modules/MigrationJobs/List/MigrationJobList.vue";

export interface MigrationJobListDialog_Input {
    workspace?: Workspace;
    deployment?: Deployment;
}

const props = defineProps<{input: MigrationJobListDialog_Input, events: DialogEventsInterface}>();

const used = ref(false);
const showDialog = ref(false);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;
});

onUnmounted(() => {
});

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
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
        width="80vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>Migration Jobs</v-card-title>
            <v-divider/>
            <v-card-text>
                <MigrationJobList
                    :show-header="false"
                    :filter-by-workspace-id="props.input.workspace?.id"
                    :filter-by-deployment-id="props.input.deployment?.id"/>
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
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.icon-wrapper{
    width: 32px;
    vertical-align: center;
    justify-content: center;
    align-items: center;
}
</style>
