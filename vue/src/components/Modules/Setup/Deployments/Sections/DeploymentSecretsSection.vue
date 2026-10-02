<script setup lang="ts">
import {Deployment} from "@/core/services/Deploy/models";
import GeneratedSecretsSection from "@/components/Modules/Common/GeneratedSecrets/GeneratedSecretsSection.vue";

defineProps<{
    deployment: Deployment
}>();
</script>

<template>
    <GeneratedSecretsSection owner="deployment" :owner-id="deployment.id!"/>
</template>
