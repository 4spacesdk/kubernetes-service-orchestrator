<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, KeelHookQueueItem} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {KeelHookStatusTypes} from "@/constants";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";
import {ChangeEvent} from "@/services/Wamp/ChangeEvent";

const props = defineProps<{
    keelHookQueueItem: KeelHookQueueItem,
}>();

const showIcon = ref(false);
const showLoader = ref(false);
const text = ref('unknown');
const icon = ref('fa-circle-info');
const color = ref('grey');
const isLoading = ref(false);
const isHovering = ref(false);
const wampSubscription = ref<WampSubscription>();

onMounted(() => {
    render(props.keelHookQueueItem.status!);

    wampSubscription.value = WampService.subscribe(
        Events.KeelHookQueueItem_Changed_Status(props.keelHookQueueItem.id!),
        data => {
            const changeEvent = new ChangeEvent<KeelHookQueueItem>(data.previous, new KeelHookQueueItem(data.next));
            render(changeEvent.next.status!);
        }
    );
});

onUnmounted(() => {
    wampSubscription.value?.unsubscribe();
});

function reload() {
    isLoading.value = true;
    Api.keelHookQueueItems().get()
        .where('id', props.keelHookQueueItem.id!)
        .find(keelHookQueueItems => {
            render(keelHookQueueItems[0].status!);
            isLoading.value = false;
        });
}

function render(status: string) {
    showIcon.value = false;
    showLoader.value = false;
    switch (status) {
        case KeelHookStatusTypes.New:
            showIcon.value = true;
            icon.value = 'fa fa-box';
            color.value = 'orange';
            text.value =  'In queue';
            break;
        case KeelHookStatusTypes.Running:
            showLoader.value = true;
            icon.value = 'fa fa-check';
            color.value = 'green';
            text.value =  'Running';
            break;
        case KeelHookStatusTypes.Finished:
            showIcon.value = true;
            icon.value = 'fa fa-check';
            color.value = 'green';
            text.value =  'Finished';
            break;
        case KeelHookStatusTypes.Error:
            showIcon.value = true;
            icon.value = 'fa fa-circle-xmark';
            color.value = 'red';
            text.value =  'Error';
            break;
        default:
            showIcon.value = true;
            icon.value = 'fa fa-circle-info';
            color.value = 'grey';
            text.value =  'Unknown';
            break;
    }
}

// <editor-fold desc="View function bindings">

function onRefreshBtnClicked() {
    reload();
}

// </editor-fold>

</script>

<template>
    <div class="d-flex justify-start align-center"
         @mouseover="isHovering = true"
         @mouseleave="isHovering = false"
    >

        <v-btn
            @click="onRefreshBtnClicked"
            v-if="!isHovering"
            :loading="isLoading"
            variant="plain" color="grey" size="small" icon
        >
            <v-icon
                v-if="showIcon"
                :color="color" class="me-1">fal {{ icon }}</v-icon>
            <v-progress-circular
                v-if="showLoader"
                class="me-1"
                width="1" indeterminate size="x-small"
                color="blue"/>
            <v-tooltip activator="parent" location="bottom" v-if="text">{{ text }}</v-tooltip>
        </v-btn>

        <v-btn
            v-if="isHovering"
            :loading="isLoading"
            variant="plain" color="grey" size="small" icon
            @click="onRefreshBtnClicked">
            <v-icon>fa fa-arrows-rotate</v-icon>
            <v-tooltip activator="parent" location="bottom">Refresh</v-tooltip>
        </v-btn>

        <span>{{ text }}</span>

    </div>
</template>

<style scoped>

</style>
