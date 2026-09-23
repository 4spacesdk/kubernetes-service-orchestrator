<script setup lang="ts">
import {ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import KubernetesLogList from "@/components/Modules/KubernetesLogs/List/KubernetesLogList.vue";

/**
 * The logs of the migration jobs' pods while the cluster still has them. What a finished job
 * wrote is kept with the job, under Migration Jobs.
 */
const props = defineProps<{
    deployment: Deployment
}>();

const kubernetesLogList = ref<InstanceType<typeof KubernetesLogList>>();
</script>

<template>
    <page-section
        title="Migration Logs"
        hide-save
        flush
        fill>
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
            role="migration"/>
    </page-section>
</template>
