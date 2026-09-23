<script setup lang="ts">
import {onMounted, onUnmounted} from 'vue'
import {useRoute, useRouter} from "vue-router";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {Project} from "@/core/services/Deploy/models";
import ProjectList from "@/components/Modules/Setup/Projects/List/ProjectList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'Projects':
            break;
        case 'ProjectById':
            Api.projects().get()
                .where('id', route.params.id)
                .find(items => {
                    if (items.length == 1) {
                        bus.emit('projectEdit', {
                            project: items[0]
                        });
                    }
                });
            break;
    }

    bus.on('projectEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('projectEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'Projects', query: router.currentRoute.value.query});
}

function onItemEditClicked(item: Project) {
    router.push({
        name: 'ProjectById',
        query: router.currentRoute.value.query,
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <ProjectList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
