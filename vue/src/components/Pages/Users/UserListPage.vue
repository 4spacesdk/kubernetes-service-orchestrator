<script setup lang="ts">
import UserList from '@/components/Modules/Users/List/UserList.vue';
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {User} from "@/core/services/Deploy/models";
import {useRoute, useRouter} from "vue-router";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'Users':
            break;
        case 'UserById':
            Api.users().get()
                .where('id', route.params.id)
                .find(items => {
                    if (items.length == 1) {
                        bus.emit('userEdit', {
                            user: items[0]
                        });
                    }
                });
            break;
    }

    bus.on('userEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('userEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'Users'});
}

function onItemEditClicked(item: User) {
    router.push({
        name: 'UserById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <UserList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
