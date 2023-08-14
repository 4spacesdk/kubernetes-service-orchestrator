<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {DeploymentPackage} from "@/core/services/Deploy/models";
import DeploymentPackageList from "@/components/Modules/Setup/DeploymentPackages/List/DeploymentPackageList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'DeploymentPackages':
            break;
    }

    bus.on('deploymentPackageEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('deploymentPackageEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'DeploymentPackages'});
}

function onItemEditClicked(item: DeploymentPackage) {
    router.push({
        name: 'DeploymentPackageById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <DeploymentPackageList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
