<script setup lang="ts">
import {onMounted, ref} from 'vue';
import {Deployment} from "@/core/services/Deploy/models";
import {Api, type DeploymentTrustedProxiesResponse} from "@/core/services/Deploy/Api";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/**
 * The deployment's way in, and the proxies on it that `${network.trustedProxies}` lists - as a
 * deploy would fill it in now, or why it could not. See `TrustedProxies`.
 */
const props = defineProps<{
    deployment: Deployment
}>();

const isLoading = ref(false);
const answer = ref<DeploymentTrustedProxiesResponse>();

onMounted(load);

function load() {
    isLoading.value = true;
    Api.deployments().getTrustedProxiesGetById(props.deployment.id!).find(responses => {
        answer.value = responses[0];
        isLoading.value = false;
    });
}
</script>

<template>
    <page-section
        title="Network"
        hide-save
        :is-loading="isLoading">
        <template #actions>
            <v-btn icon variant="plain" color="secondary" size="small" @click="load">
                <v-icon>fa fa-rotate</v-icon>
                <v-tooltip activator="parent" location="bottom">Work it out again</v-tooltip>
            </v-btn>
        </template>

        <div v-if="answer" class="section-body">
            <div class="text-medium-emphasis mb-1">Way in</div>
            <div class="mb-4">{{ answer.way }}</div>

            <div class="text-medium-emphasis mb-1">Trusted proxies - <code>${network.trustedProxies}</code></div>
            <div v-if="answer.value" class="d-flex flex-wrap ga-1">
                <v-chip v-for="range in answer.value.split(',')" :key="range" size="small" label variant="tonal">{{ range }}</v-chip>
            </div>
            <v-alert v-else type="warning" variant="tonal" density="compact">
                {{ answer.error }}
                <div class="text-body-small mt-1">A deploy whose variables use <code>${network.trustedProxies}</code> stops here until it can be told.</div>
            </v-alert>
            <div class="text-body-small text-medium-emphasis mt-4">
                For an app that reads the client's address from X-Forwarded-For: from the right, past every proxy it trusts, the first address
                that is not one is the client. Worked out at each deploy, so a new node pool or a new Gateway address comes along with the next one.
            </div>
        </div>
    </page-section>
</template>

<style scoped>
.section-body {
    max-width: 720px;
}
</style>
