<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {PodioIntegration} from "@/core/services/Deploy/models";
import PodioIntegrationList from "@/components/Modules/Integrations/PodioIntegrations/List/PodioIntegrationList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'PodioIntegrations':
            break;
    }

    bus.on('podioIntegrationEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('podioIntegrationEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'PodioIntegrations'});
}

function onItemEditClicked(item: PodioIntegration) {
    router.push({
        name: 'PodioIntegrationById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <PodioIntegrationList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
