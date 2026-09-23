<script setup lang="ts">
import {computed, onMounted, onUnmounted, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import bus from "@/plugins/bus";
import {goBack} from "@/helpers/goBack";
import {Api} from "@/core/services/Deploy/Api";
import {Workspace} from "@/core/services/Deploy/models";
import {useWorkspaceActions} from "@/composables/useWorkspaceActions";
import DetailPageLayout from "@/components/Modules/Common/DetailPage/DetailPageLayout.vue";
import {workspaceSections} from "@/components/Modules/Workspaces/Sections/sections";

/**
 * A workspace: how it is doing and where it answers on the overview, and what it runs and is
 * set up with in the sections beside it. Each deployment opens on a page of its own.
 */
const route = useRoute();
const router = useRouter();

const item = ref<Workspace>();
const isNotFound = ref(false);

const id = computed(() => parseInt(route.params.id as string));

const {rbacWorkspaceCreate, deploy} = useWorkspaceActions();

onMounted(() => {
    bus.on('workspaceSaved', onSaved);
    bus.on('deploymentSaved', onSaved);
    load();
});

onUnmounted(() => {
    bus.off('workspaceSaved', onSaved);
    bus.off('deploymentSaved', onSaved);
});

watch(id, () => {
    item.value = undefined;
    load();
});

function load() {
    isNotFound.value = false;
    Api.workspaces().getById(id.value)
        .include('deployment')
        .include('domain')
        .include('email_service')
        .include('database_service')
        .include('label')
        .include('workspace_template')
        .include('project')
        .find(items => {
            item.value = items[0];
            isNotFound.value = !items[0];
            if (items[0]) {
                document.title = items[0].name_readable ?? document.title;
            }
        });
}

function onSaved() {
    load();
}

function onBack() {
    goBack(router, {name: 'WorkspacesOverview'});
}

</script>

<template>
    <detail-page-layout
        :item="item"
        :is-not-found="isNotFound"
        :sections="workspaceSections"
        :section-props="{workspace: item}"
        route-name="WorkspaceById"
        not-found-text="This workspace does not exist."
        @back="onBack">
        <template #title>
            <span>{{ item!.name_readable }}</span>
            <span class="namespace ml-2">{{ item!.namespace }}</span>
        </template>
        <template #actions>
            <v-btn
                v-if="rbacWorkspaceCreate"
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
