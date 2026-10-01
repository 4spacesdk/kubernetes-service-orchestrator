<script setup lang="ts">
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import DeploymentSpecificationInitContainersSection from "./DeploymentSpecificationInitContainersSection.vue";

/** The sidecars: init containers in Kubernetes, a list of their own here - see the section it wraps. */
defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();
</script>

<template>
    <DeploymentSpecificationInitContainersSection
        :deployment-specification="deploymentSpecification"
        sidecars/>
</template>
