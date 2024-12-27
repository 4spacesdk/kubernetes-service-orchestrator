<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue';
import moment from 'moment';
import {Domain} from "@/core/services/Deploy/models";

const props = defineProps<{
    color?: string;
}>();

const emit = defineEmits<{
    (e: 'addVariable', item: string): void
}>();

interface Variable {
    name: string;
    code: string;
}

const showVariablesMenu = ref(false);
const variables = ref<Variable[]>([
    {
        name: "Namespace",
        code: "${namespace}"
    },
    {
        name: "Database Host",
        code: "${database.host}"
    },
    {
        name: "Database Name",
        code: "${database.name}"
    },
    {
        name: "Database User",
        code: "${database.user}"
    },
    {
        name: "Database Password",
        code: "${database.pass}"
    },
    {
        name: "Email Service Host",
        code: "${emailService.host}"
    },
    {
        name: "Email Service Port",
        code: "${emailService.port}"
    },
    {
        name: "Email Service User",
        code: "${emailService.user}"
    },
    {
        name: "Email Service Pass",
        code: "${emailService.pass}"
    },
    {
        name: "Email Service Sender",
        code: "${emailService.sender}"
    },
    {
        name: "Domain Host",
        code: "${domain.host}"
    },
    {
        name: "Deployment Name",
        code: "${deployment.name}"
    },
    {
        name: "Deployment Subdomain",
        code: "${deployment.subdomain}"
    },
    {
        name: "Workspace Id",
        code: "${workspace.id}"
    },
    {
        name: "Workspace Name",
        code: "${workspace.name}"
    },
    {
        name: "Migration Job Name",
        code: "${migration.job.name}"
    },
]);

onMounted(() => {

});

function onVariableClicked(variable: Variable) {
    emit('addVariable', variable.code);
    showVariablesMenu.value = false;
}

</script>

<template>
    <v-menu
        v-model="showVariablesMenu"
        :close-on-content-click="false"
        left
        min-width="250"
        offset-y>
        <template v-slot:activator="{ props }">
            <v-btn
                v-bind="props"
                icon
                variant="plain"
                :color="color ?? 'primary'"
                size="small">
                <v-icon>fa fa-key</v-icon>
                <v-tooltip activator="parent" location="bottom">Insert variable</v-tooltip>
            </v-btn>
        </template>

        <v-list
            class="list-items">
            <v-list-item
                v-for="(variable, i) in variables" :key="i"
                dense
                @click="onVariableClicked(variable)">
                <v-list-item-title>
                    <v-icon size="small" class="my-auto">fa fa-window-maximize fa</v-icon>
                    <span class="ml-2">{{ variable.name }}</span>
                </v-list-item-title>
            </v-list-item>
        </v-list>
    </v-menu>
</template>
