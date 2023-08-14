<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {Domain} from "@/core/services/Deploy/models";
import DomainList from "@/components/Modules/Setup/Domains/List/DomainList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'Domains':
            break;
    }

    bus.on('domainEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('domainEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'Domains'});
}

function onItemEditClicked(item: Domain) {
    router.push({
        name: 'DomainById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <DomainList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
