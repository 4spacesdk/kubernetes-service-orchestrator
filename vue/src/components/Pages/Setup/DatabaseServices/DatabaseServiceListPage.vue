<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {DatabaseService} from "@/core/services/Deploy/models";
import DatabaseServiceList from "@/components/Modules/Setup/DatabaseServices/List/DatabaseServiceList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'DatabaseServices':
            break;
        case 'DatabaseServiceById':
            Api.databaseServices().get()
                .where('id', route.params.id)
                .find(items => {
                    if (items.length == 1) {
                        bus.emit('databaseServiceEdit', {
                            databaseService: items[0]
                        });
                    }
                });
            break;
    }

    bus.on('databaseServiceEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('databaseServiceEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'DatabaseServices'});
}

function onItemEditClicked(item: DatabaseService) {
    router.push({
        name: 'DatabaseServiceById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <DatabaseServiceList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
