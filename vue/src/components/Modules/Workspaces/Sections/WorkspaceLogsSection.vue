<script setup lang="ts">
import {ref} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import KubernetesLogList from "@/components/Modules/KubernetesLogs/List/KubernetesLogList.vue";

const props = defineProps<{
    workspace: Workspace
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
            :namespace="props.workspace.namespace"/>
    </page-section>
</template>
