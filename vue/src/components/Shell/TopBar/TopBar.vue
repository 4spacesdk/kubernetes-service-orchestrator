<script setup lang="ts">
import TopBarMenu from '@/components/Shell/TopBar/TopBar-Menu.vue';

</script>

<template>
    <v-system-bar app color="primary"
                  class="px-2"
                  height="65">

        <div class="d-flex h-100 align-start flex-column">
            <div class="logo"></div>
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
.logo{
    background: url("/logo.svg");
    background-repeat: no-repeat;
    aspect-ratio: auto;
    display: flex;
    width: 120px;
    height: 45px;
    margin-top: .25rem;
}

</style>
