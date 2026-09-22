<script setup lang="ts">
import {Deployment} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import MigrationJobList from "@/components/Modules/MigrationJobs/List/MigrationJobList.vue";

const props = defineProps<{
    deployment: Deployment
}>();
</script>

<template>
    <page-section
        title="Migration Jobs"
        hide-save>
        <migration-job-list
            :show-header="false"
            :filter-by-deployment-id="props.deployment.id"/>
    </page-section>
</template>
