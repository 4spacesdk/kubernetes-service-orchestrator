<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {OAuthClient} from "@/core/services/Deploy/models";
import OAuthClientList from "@/components/Modules/Integrations/OAuthClients/List/OAuthClientList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'OAuthClients':
            break;
    }

    bus.on('oauthClientEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('oauthClientEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'OAuthClients'});
}

function onItemEditClicked(item: OAuthClient) {
    router.push({
        name: 'OAuthClientById',
        params: {
            id: item.client_id
        }
    });
}

</script>

<template>
    <OAuthClientList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
