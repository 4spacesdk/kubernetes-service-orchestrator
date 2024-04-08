<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import AutoUpdateList from "@/components/Modules/AutoUpdates/List/AutoUpdateList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'AutoUpdates':
            break;
    }
});

onUnmounted(() => {
});

</script>

<template>
    <AutoUpdateList
        :show-header="true"/>
</template>

<style scoped>
</style>
