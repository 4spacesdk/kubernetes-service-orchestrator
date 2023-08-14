<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {Deployment} from "@/core/services/Deploy/models";
import DeploymentList from "@/components/Modules/Setup/Deployments/List/DeploymentList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'Deployments':
            break;
    }
});

onUnmounted(() => {
});

</script>

<template>
    <DeploymentList
        :show-header="true"
        :show-create-btn="true"/>
</template>

<style scoped>
</style>
