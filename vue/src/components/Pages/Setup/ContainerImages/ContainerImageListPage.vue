<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {ContainerImage} from "@/core/services/Deploy/models";
import ContainerImageList from "@/components/Modules/Setup/ContainerImages/List/ContainerImageList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'ContainerImages':
            break;
    }

    bus.on('containerImageEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('containerImageEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'ContainerImages'});
}

function onItemEditClicked(item: ContainerImage) {
    router.push({
        name: 'ContainerImageById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <ContainerImageList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
