<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import DeploymentSpecificationList from "@/components/Modules/Setup/DeploymentSpecifications/List/DeploymentSpecificationList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'DeploymentSpecifications':
            break;
    }

    bus.on('deploymentSpecificationEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('deploymentSpecificationEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'DeploymentSpecifications'});
}

function onItemEditClicked(item: DeploymentSpecification) {
    router.push({
        name: 'DeploymentSpecificationById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <DeploymentSpecificationList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
