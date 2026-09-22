<script setup lang="ts">
import {computed, onMounted, onUnmounted, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {goBack} from "@/helpers/goBack";
import {useDeploymentActions} from "@/composables/useDeploymentActions";
import {Api} from "@/core/services/Deploy/Api";
import {Deployment} from "@/core/services/Deploy/models";
import DetailPageLayout from "@/components/Modules/Common/DetailPage/DetailPageLayout.vue";
import {deploymentSections} from "@/components/Modules/Setup/Deployments/Sections/sections";

const route = useRoute();
const router = useRouter();

const item = ref<Deployment>();
const {deploy} = useDeploymentActions();
const isNotFound = ref(false);

const id = computed(() => parseInt(route.params.id as string));

onMounted(() => {
    bus.on('deploymentSaved', onSaved);
    load();
});

onUnmounted(() => {
    bus.off('deploymentSaved', onSaved);
});

watch(id, () => {
    item.value = undefined;
    load();
});

function load() {
    isNotFound.value = false;
    Api.deployments().getById(id.value)
        .include('deployment_specification')
        .include('workspace')
        .find(items => {
            item.value = items[0];
            isNotFound.value = !items[0];
            if (items[0]) {
                document.title = items[0].name ?? document.title;
            }
        });
}

/** A section saved: status and what the menu shows may follow from it. */
function onSaved(saved?: Deployment) {
    if (!saved || saved.id == id.value) {
        load();
    }
}

function onBack() {
    goBack(router, {name: 'Deployments'});
}

</script>

<template>
    <detail-page-layout
        :item="item"
        :is-not-found="isNotFound"
        :sections="deploymentSections"
        :section-props="{deployment: item}"
        route-name="DeploymentById"
        not-found-text="This deployment does not exist."
        @back="onBack">
        <template #title>
            <span>{{ item!.name }}</span>
            <span class="namespace ml-2">{{ item!.namespace }}</span>
        </template>
        <template #actions>
            <v-btn
                prepend-icon="fa fa-play"
                @click="deploy(item!)">
                Deploy
            </v-btn>
        </template>
    </detail-page-layout>
</template>

<style scoped>
.namespace {
    font-size: 12px;
    opacity: 0.7;
}
</style>
