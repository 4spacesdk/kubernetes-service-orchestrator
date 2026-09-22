<script setup lang="ts">
import {onMounted, ref} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import AuditEventList from "@/components/Modules/AuditEvents/List/AuditEventList.vue";

export interface AuditEventListDialog_Input {
    /** As the trail names it: the entity's class, such as Deployment. */
    resourceType: string;
    resourceId: number;
    title?: string;
}

const props = defineProps<{input: AuditEventListDialog_Input, events: DialogEventsInterface}>();

const showDialog = ref(false);

onMounted(() => {
    showDialog.value = true;
});

function close() {
    showDialog.value = false;
    props.events.onClose();
}

</script>

<template>
    <v-dialog
        persistent
        height="70vh"
        width="80vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>History<span v-if="props.input.title">: {{ props.input.title }}</span></v-card-title>
            <v-divider/>
            <v-card-text>
                <AuditEventList
                    :show-header="false"
                    :filter-by-resource-type="props.input.resourceType"
                    :filter-by-resource-id="props.input.resourceId"/>
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="close">
                    Close
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
