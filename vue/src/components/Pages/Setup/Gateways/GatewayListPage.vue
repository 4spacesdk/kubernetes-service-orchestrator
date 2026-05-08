<script setup lang="ts">
import {onMounted, onUnmounted} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {Gateway} from "@/core/services/Deploy/models";
import GatewayList from "@/components/Modules/Setup/Gateways/List/GatewayList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'Gateways':
            break;
    }

    bus.on('gatewayEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('gatewayEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'Gateways'});
}

function onItemEditClicked(item: Gateway) {
    router.push({
        name: 'GatewaysById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <GatewayList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
