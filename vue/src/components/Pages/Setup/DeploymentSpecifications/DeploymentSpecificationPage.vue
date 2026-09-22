<script setup lang="ts">
import {computed, onMounted, onUnmounted, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {goBack} from "@/helpers/goBack";
import {Api} from "@/core/services/Deploy/Api";
import {DeploymentSpecification} from "@/core/services/Deploy/models";
import DetailPageLayout from "@/components/Modules/Common/DetailPage/DetailPageLayout.vue";
import {
    deploymentSpecificationSections,
    groupTitle
} from "@/components/Modules/Setup/DeploymentSpecifications/Sections/sections";

const route = useRoute();
const router = useRouter();

const item = ref<DeploymentSpecification>();
const isNotFound = ref(false);

const id = computed(() => parseInt(route.params.id as string));

onMounted(() => {
    bus.on('deploymentSpecificationSaved', onSaved);
    load();
});

onUnmounted(() => {
    bus.off('deploymentSpecificationSaved', onSaved);
});

watch(id, () => {
    item.value = undefined;
    load();
});

function load() {
    isNotFound.value = false;
    Api.deploymentSpecifications().getById(id.value)
        .include('container_image')
        .find(items => {
            item.value = items[0];
            isNotFound.value = !items[0];
            if (items[0]) {
                document.title = items[0].name ?? document.title;
            }
        });
}

/** A section saved: what the menu shows may follow from it, so read the specification again. */
function onSaved() {
    load();
}

function onBack() {
    goBack(router, {name: 'DeploymentSpecifications'});
}

</script>

<template>
    <detail-page-layout
        :item="item"
        :is-not-found="isNotFound"
        :sections="deploymentSpecificationSections"
        :group-title="groupTitle"
        :section-props="{deploymentSpecification: item}"
        route-name="DeploymentSpecificationById"
        not-found-text="This deployment specification does not exist."
        @back="onBack">
        <template #title>
            <span>{{ item!.name }}</span>
            <v-chip
                size="small"
                class="ml-2">{{ item!.workload_type }}</v-chip>
        </template>
    </detail-page-layout>
</template>
