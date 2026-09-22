<script setup lang="ts">
import {Deployment} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import AuditEventList from "@/components/Modules/AuditEvents/List/AuditEventList.vue";

const props = defineProps<{
    deployment: Deployment
}>();
</script>

<template>
    <page-section
        title="History"
        hide-save>
        <audit-event-list
            :show-header="false"
            filter-by-resource-type="Deployment"
            :filter-by-resource-id="props.deployment.id"/>
    </page-section>
</template>
