<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref} from 'vue'
import {versions} from "@/versions";
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesNodeInfo} from "@/core/services/Deploy/Api";

const version = ref(versions.version);

const status = ref<string>('');
const message = ref<string>('');
const nodes = ref<KubernetesNodeInfo[]>([]);

onMounted(() => {
    getStatus();
    setInterval(() => {
        getStatus();
    }, 5000);
});

function getStatus() {
    Api.kubernetes().nodeInfoGet().find(value => {
        status.value = value[0].status ?? '';
        message.value = value[0].message ?? '';
        nodes.value = value[0].nodes ?? [];
    });
}

</script>

<template>
    <v-footer
        border
        height="30px"
        absolute
        app
    >
        <small>{{ version }}</small>

        <div
            class="ml-auto d-flex"
        >
            <v-badge
                style="margin-bottom: 4px;"
                :color="status == 'success' ? 'success' : 'error'"
            >
                <v-tooltip activator="parent" location="top">
                    {{ status == 'success' ? 'Connected' : message }}
                </v-tooltip>
            </v-badge>
        </div>
    </v-footer>
</template>

<style scoped>

</style>
