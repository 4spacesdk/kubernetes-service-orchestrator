<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {Webhook} from "@/core/services/Deploy/models";
import WebhookList from "@/components/Modules/Integrations/Webhooks/List/WebhookList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'Webhooks':
            break;
    }

    bus.on('webhookEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('webhookEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'Webhooks'});
}

function onItemEditClicked(item: Webhook) {
    router.push({
        name: 'WebhookById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <WebhookList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
