<script setup lang="ts">
import {ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import KubernetesLogList from "@/components/Modules/KubernetesLogs/List/KubernetesLogList.vue";

/**
 * The logs of the cron jobs' pods - those on schedule and those run now - while the cluster still
 * has them: each cron job keeps as many of its jobs as its history limits say. They carry no
 * `app`, so they are found by the deployment's label.
 */
const props = defineProps<{
    deployment: Deployment
}>();

const kubernetesLogList = ref<InstanceType<typeof KubernetesLogList>>();
</script>

<template>
    <page-section
        title="Cron Job Logs"
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
            :deployment-name="props.deployment.name"
            role="cron"/>
    </page-section>
</template>
