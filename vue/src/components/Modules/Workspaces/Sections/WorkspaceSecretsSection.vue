<script setup lang="ts">
import {Workspace} from "@/core/services/Deploy/models";
import GeneratedSecretsSection from "@/components/Modules/Common/GeneratedSecrets/GeneratedSecretsSection.vue";

defineProps<{
    workspace: Workspace
}>();
</script>

<template>
    <GeneratedSecretsSection owner="workspace" :owner-id="workspace.id!"/>
</template>
