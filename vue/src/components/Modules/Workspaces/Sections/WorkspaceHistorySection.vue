<script setup lang="ts">
import {Workspace} from "@/core/services/Deploy/models";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";
import AuditEventList from "@/components/Modules/AuditEvents/List/AuditEventList.vue";

const props = defineProps<{
    workspace: Workspace
}>();
</script>

<template>
    <page-section
        title="History"
        hide-save
        flush>
        <audit-event-list
            :show-header="false"
            filter-by-resource-type="Workspace"
            :filter-by-resource-id="props.workspace.id"/>
    </page-section>
</template>
