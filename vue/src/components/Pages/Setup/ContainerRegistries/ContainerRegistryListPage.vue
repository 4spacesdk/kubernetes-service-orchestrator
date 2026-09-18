<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {ContainerRegistry} from "@/core/services/Deploy/models";
import ContainerRegistryList from "@/components/Modules/Setup/ContainerRegistries/List/ContainerRegistryList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'ContainerRegistries':
            break;
        case 'ContainerRegistryById':
            Api.containerRegistries().get()
                .where('id', route.params.id)
                .find(items => {
                    if (items.length == 1) {
                        bus.emit('containerRegistryEdit', {
                            containerRegistry: items[0]
                        });
                    }
                });
            break;
    }

    bus.on('containerRegistryEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('containerRegistryEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'ContainerRegistries'});
}

function onItemEditClicked(item: ContainerRegistry) {
    router.push({
        name: 'ContainerRegistryById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <ContainerRegistryList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
