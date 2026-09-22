<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {WorkspaceTemplate} from "@/core/services/Deploy/models";
import WorkspaceTemplateList from "@/components/Modules/Setup/WorkspaceTemplates/List/WorkspaceTemplateList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'WorkspaceTemplates':
            break;
    }

    bus.on('workspaceTemplateEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('workspaceTemplateEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'WorkspaceTemplates', query: router.currentRoute.value.query});
}

function onItemEditClicked(item: WorkspaceTemplate) {
    router.push({
        name: 'WorkspaceTemplateById',
        query: router.currentRoute.value.query,
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <WorkspaceTemplateList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
