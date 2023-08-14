<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import KeelHookQueueItemList from "@/components/Modules/KeelHookQueueItems/List/KeelHookQueueItemList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'KeelHookQueueItems':
            break;
    }
});

onUnmounted(() => {
});

</script>

<template>
    <KeelHookQueueItemList
        :show-header="true"/>
</template>

<style scoped>
</style>
