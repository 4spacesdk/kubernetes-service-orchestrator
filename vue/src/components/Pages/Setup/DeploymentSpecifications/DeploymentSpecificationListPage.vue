<script setup lang="ts">
import {useRouter} from "vue-router";
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import DeploymentSpecificationList from "@/components/Modules/Setup/DeploymentSpecifications/List/DeploymentSpecificationList.vue";

const router = useRouter();

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
