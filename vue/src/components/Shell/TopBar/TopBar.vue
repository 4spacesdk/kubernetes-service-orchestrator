<script setup lang="ts">
import TopBarMenu from '@/components/Shell/TopBar/TopBar-Menu.vue';

</script>

<template>
    <v-system-bar app color="primary"
                  class="px-2"
                  height="65">

        <div
            class="d-flex h-100 align-start flex-column ml-1"
        >
            <v-img
                src="/app/logo-white.svg"
                min-height="50"
                min-width="50"
            />
        </div>

        <v-spacer></v-spacer>

        <div
            class="ml-auto my-auto d-flex">
            <TopBarMenu
                class="my-auto"/>
        </div>
    </v-system-bar>
</template>

<style scoped>

</style>
