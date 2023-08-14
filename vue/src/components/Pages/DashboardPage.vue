<script setup lang="ts">
import {onMounted} from "vue";
import {useRouter} from "vue-router";

onMounted(() => {
    useRouter().push('Workspaces');
});
</script>

<template>
    <div class="h-100 w-100">
        <v-card ref="card"
                color="#fff"
                flat>
            <v-card-title>Dashboard</v-card-title>
            <v-card-text>

            </v-card-text>
        </v-card>
    </div>
</template>

<style scoped>

</style>
