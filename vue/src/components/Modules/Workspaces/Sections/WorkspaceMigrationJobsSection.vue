<script setup lang="ts">
import {Workspace} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import MigrationJobList from "@/components/Modules/MigrationJobs/List/MigrationJobList.vue";

const props = defineProps<{
    workspace: Workspace
}>();
</script>

<template>
    <page-section
        title="Migration Jobs"
        hide-save
        flush>
        <migration-job-list
            :show-header="false"
            :filter-by-workspace-id="props.workspace.id"/>
    </page-section>
</template>
