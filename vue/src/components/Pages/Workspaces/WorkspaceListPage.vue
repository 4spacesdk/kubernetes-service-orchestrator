<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import WorkspaceList from "@/components/Modules/Workspaces/List/WorkspaceList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'Workspaces':
            break;
    }
});

onUnmounted(() => {
});

</script>

<template>
    <WorkspaceList/>
</template>

<style scoped>
</style>
