<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRoute, useRouter} from "vue-router";
import WorkspaceList from "@/components/Modules/Workspaces/List/WorkspaceList.vue";
import ApiService from "@/services/ApiService";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

const router = useRouter();
const url = ref<string>();
const error = ref<string>();

onMounted(() => {
    const route = useRoute();

    const api = Api.workspaces().requestSupportLoginGetById(parseInt(route.params.id as string))
    api.setErrorHandler(response => {
        error.value = response.error;
        if (response.error) {
            bus.emit('toast', {
                text: response.error,
                icon: 'fa fa-triangle-exclamation',
            });
        }
        return false;
    });
    api.find(response => {
        url.value = response[0].value;
        window.open(response[0].value, '_blank')?.focus();
    });
});

onUnmounted(() => {
});

</script>

<template>
    <v-container>
        <p v-if="!url && !error" class="text-grey">
            <v-icon class="mr-1">fa fa-shield-halved</v-icon>
            Authenticating support request...</p>
        <p v-if="url">
            Please accept popup window to access workspace. Click <a :href="url" target="_blank">here</a> if no popup is available. <a href="#" onclick="window.location.reload()">Reload</a>.
        </p>
        <p v-if="error">
            <v-icon class="mr-1 text-orange">fa fa-triangle-exclamation</v-icon>
            An error occurred: {{error}}

            <a href="#" onclick="window.location.reload()">Reload</a>.
        </p>
    </v-container>
</template>

<style scoped>
</style>
