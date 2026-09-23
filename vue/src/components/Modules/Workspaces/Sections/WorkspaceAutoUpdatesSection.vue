<script setup lang="ts">
import {ref} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import AutoUpdateList from "@/components/Modules/AutoUpdates/List/AutoUpdateList.vue";

/** The updates waiting for, or rolled out to, this workspace's deployments. */
const props = defineProps<{
    workspace: Workspace
}>();

const list = ref<InstanceType<typeof AutoUpdateList>>();
</script>

<template>
    <page-section
        title="Updates"
        hide-save
        flush>
        <template #actions>
            <v-btn
                :disabled="!list?.selectedCount"
                :loading="list?.isApproving"
                variant="outlined"
                color="success"
                size="small"
                prepend-icon="fa fa-check"
                @click="list?.approveSelected()">
                Approve
            </v-btn>
        </template>
        <auto-update-list
            ref="list"
            :show-header="false"
            :filter-by-workspace-id="props.workspace.id"/>
    </page-section>
</template>
