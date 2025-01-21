<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {System} from "@/core/services/Deploy/models";
import {NetworkTypes} from "@/constants";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

const isLoading = ref(false);
const value = ref<System | null>(null);
const networkTypes = ref([
    {
        name: 'Nginx Ingress',
        value: NetworkTypes.NginxIngress,
    },
    {
        name: 'Istio',
        value: NetworkTypes.Istio,
    },
    {
        name: 'Contour',
        value: NetworkTypes.Contour,
    },
]);
const selectedNetworkTypes = ref<string[]>([]);

onMounted(() => {
    value.value = new System(System.Instance);

    if (value.value.is_network_nginx_ingress_supported) {
        selectedNetworkTypes.value.push(NetworkTypes.NginxIngress);
    }
    if (value.value.is_network_istio_supported) {
        selectedNetworkTypes.value.push(NetworkTypes.Istio);
    }
    if (value.value.is_network_contour_supported) {
        selectedNetworkTypes.value.push(NetworkTypes.Contour);
    }
});

function onSaveBtnClicked() {
    value.value!.is_network_nginx_ingress_supported = selectedNetworkTypes.value.includes(NetworkTypes.NginxIngress);
    value.value!.is_network_istio_supported = selectedNetworkTypes.value.includes(NetworkTypes.Istio);
    value.value!.is_network_contour_supported = selectedNetworkTypes.value.includes(NetworkTypes.Contour);
    Api.systems().patchById(value.value!.id!)
        .save(value.value!, system => {
            System.Instance = system;
            bus.emit('toast', {
                text: 'Saved'
            });
        });
}

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            density="compact"
            flat
            color="blue-grey lighten-5"
            dark
        >
            <v-toolbar-title>System</v-toolbar-title>
        </v-toolbar>

        <v-progress-linear
            v-if="isLoading"
            indeterminate
            color="accent"
        />

        <v-row
            class="pa-4"
            v-if="!isLoading && value"
        >
            <v-col cols="4">
                <v-select
                    v-model="selectedNetworkTypes"
                    :items="networkTypes"
                    item-title="name"
                    item-value="value"
                    label="Supported Network Types"
                    variant="outlined"
                    multiple
                    density="compact"
                    hide-details
                />
            </v-col>
            <v-col cols="12">
                <v-btn
                    @click="onSaveBtnClicked"
                >Save</v-btn>
            </v-col>
        </v-row>
    </div>
</template>

<style scoped>

</style>
