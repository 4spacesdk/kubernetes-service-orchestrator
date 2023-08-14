<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import MigrationJobList from "@/components/Modules/MigrationJobs/List/MigrationJobList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'MigrationJobs':
            break;
    }
});

onUnmounted(() => {
});

</script>

<template>
    <MigrationJobList
        :show-header="true"/>
</template>

<style scoped>
</style>
