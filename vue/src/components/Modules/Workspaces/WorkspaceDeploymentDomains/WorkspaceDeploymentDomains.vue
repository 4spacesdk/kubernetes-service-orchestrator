<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {Deployment, Domain, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import {DeploymentStatusTypes} from "@/constants";
import {WampSubscription} from "@/services/Wamp/WampSubscription";
import WampService from "@/services/Wamp/WampService";
import {Events} from "@/services/Wamp/Events";
import {ChangeEvent} from "@/services/Wamp/ChangeEvent";
import {de} from "vuetify/locale";

const props = defineProps<{
    workspace: Workspace,
}>();

interface Row {

    deployment: Deployment,
    url: string,

}

const text = ref('');
const rows = ref<Row[]>([]);

onMounted(() => {
    setup();
});

watch(() => props.workspace, newWorkspace => {
    setup();
});

onUnmounted(() => {

});

function setup() {
    rows.value = props.workspace.deployments
        ?.filter(deployment => deployment.deployment_specification?.enable_external_access ?? false)
        ?.map(deployment => {
            return {
                deployment: deployment,
                url: deployment.url_external ?? 'missing url',
                /*url: deployment.deployment_specification?.generateUrl(
                    props.workspace.subdomain ?? '',
                    props.workspace.domain ?? new Domain(),
                    true,
                    true
                ) ?? 'missing url',*/
            };
        }) ?? [];

    rows.value.sort((a, b) => a.url.localeCompare(b.url));

    text.value = rows.value.length > 0 ? rows.value[0].url : '';

    render();
}

function reload() {

}

function render() {

}

// <editor-fold desc="View function bindings">

function onDeploymentRowClicked(item: Row) {
    window.open(item.url, '_blank');
}

// </editor-fold>

</script>

<template>
    <v-menu
        left
        min-width="250"
        offset-y
        :open-on-hover="true"
    >
        <template v-slot:activator="{props}">
            <div
                class="d-flex justify-start align-center"
                v-bind="props"
            >
                <span class="text-truncate">{{ text }}</span>
            </div>
        </template>

        <v-card
            class="w-100 list-wrapper"
        >
            <v-list
                class="list-items"
            >
                <v-list-item
                    v-for="(row, i) in rows" :key="i"
                    @click="onDeploymentRowClicked(row)"
                >
                    <v-list-item-title>
                        {{ row.deployment.name }}
                    </v-list-item-title>
                    <v-list-item-subtitle>
                        {{ row.url }}
                    </v-list-item-subtitle>
                </v-list-item>
            </v-list>
        </v-card>
    </v-menu>
</template>

<style scoped>

.list-wrapper {
    min-width: 120px;
}

.v-list-item {
    min-height: unset;
}

.v-list-item-title {
    font-size: 11px !important;
}

</style>
