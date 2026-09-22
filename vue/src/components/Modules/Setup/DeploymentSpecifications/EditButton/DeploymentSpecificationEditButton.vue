<script setup lang="ts">
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import DetailSectionMenu from "@/components/Modules/Common/DetailPage/DetailSectionMenu.vue";
import {
    deploymentSpecificationSections,
    groupTitle
} from "@/components/Modules/Setup/DeploymentSpecifications/Sections/sections";

const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

</script>

<template>
    <detail-section-menu
        :item="props.deploymentSpecification"
        :id="props.deploymentSpecification.id!"
        :sections="deploymentSpecificationSections"
        :group-title="groupTitle"
        route-name="DeploymentSpecificationById"/>
</template>
