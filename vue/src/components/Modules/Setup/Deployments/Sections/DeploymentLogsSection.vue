<script setup lang="ts">
import {ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import KubernetesLogList from "@/components/Modules/KubernetesLogs/List/KubernetesLogList.vue";

/**
 * The deployment's pods' logs - every pod as one, or one at a time. A custom resource's are its
 * operator's pods, found by who owns them.
 */
const props = defineProps<{
    deployment: Deployment
}>();

const kubernetesLogList = ref<InstanceType<typeof KubernetesLogList>>();
</script>

<template>
    <page-section
        title="Kubernetes Logs"
        hide-save
        flush>
        <template #actions>
            <v-btn
                variant="text"
                prepend-icon="fa fa-refresh"
                @click="kubernetesLogList?.reload()">
                Reload
            </v-btn>
        </template>
        <KubernetesLogList
            ref="kubernetesLogList"
            :show-header="false"
            :namespace="props.deployment.namespace"
            :app="props.deployment.name"
            :deployment-id="props.deployment.id"
            role="app"/>
    </page-section>
</template>
