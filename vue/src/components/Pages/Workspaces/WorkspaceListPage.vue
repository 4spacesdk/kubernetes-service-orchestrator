<script setup lang="ts">
import {computed} from 'vue'
import {useRoute} from "vue-router";
import WorkspaceList from "@/components/Modules/Workspaces/List/WorkspaceList.vue";

const route = useRoute();

/** Every workspace, or one project's - `none` for those in no project. */
const project = computed<number | 'none' | undefined>(() => {
    const value = route.params.project;
    if (value === undefined) {
        return undefined;
    }
    return value == 'none' ? 'none' : parseInt(String(value));
});
</script>

<template>
    <WorkspaceList :project="project"/>
</template>
