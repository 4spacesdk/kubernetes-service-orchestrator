<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import SystemSettings from "@/components/Modules/Setup/System/SystemSettings.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'SystemPage':
            break;
    }
});

onUnmounted(() => {

});

</script>

<template>
    <SystemSettings/>
</template>

<style scoped>
</style>
