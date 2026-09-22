<script setup lang="ts">
import {Deployment} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import DeploymentResources from "@/components/Modules/Setup/Deployments/Resources/DeploymentResources.vue";

const props = defineProps<{
    deployment: Deployment
}>();
</script>

<template>
    <page-section
        title="Resources"
        hide-save>
        <deployment-resources :deployment="props.deployment"/>
    </page-section>
</template>
