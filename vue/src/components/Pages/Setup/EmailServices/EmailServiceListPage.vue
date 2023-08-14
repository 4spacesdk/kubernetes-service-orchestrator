<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {EmailService} from "@/core/services/Deploy/models";
import EmailServiceList from "@/components/Modules/Setup/EmailServices/List/EmailServiceList.vue";

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    switch (route.name) {
        case 'EmailServices':
            break;
        case 'EmailServiceById':
            Api.emailServices().get()
                .where('id', route.params.id)
                .find(items => {
                    if (items.length == 1) {
                        bus.emit('emailServiceEdit', {
                            emailService: items[0]
                        });
                    }
                });
            break;
    }

    bus.on('emailServiceEditDialog_closed', onItemEditDialog_Closed);
});

onUnmounted(() => {
    bus.off('emailServiceEditDialog_closed', onItemEditDialog_Closed);
});

function onItemEditDialog_Closed() {
    router.push({name: 'EmailServices'});
}

function onItemEditClicked(item: EmailService) {
    router.push({
        name: 'EmailServiceById',
        params: {
            id: item.id
        }
    });
}

</script>

<template>
    <EmailServiceList
        @on-item-edit-clicked="onItemEditClicked"/>
</template>

<style scoped>
</style>
