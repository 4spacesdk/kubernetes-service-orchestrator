<script setup lang="ts">
import TopBarMenu from '@/components/Shell/TopBar/TopBar-Menu.vue';
import {useDisplay} from "vuetify";

/** Lower on a phone, where every row of height is one less row of the page. */
const {xs: isPhone} = useDisplay();

/** In `public/`, served under the app's base (`/app/`), which Vite knows and the template does not. */
const logo = `${import.meta.env.BASE_URL}kso.svg`;

</script>

<template>
    <v-system-bar app color="appbar"
                  class="px-2"
                  :height="isPhone ? 48 : 65">

        <router-link
            to="/"
            class="brand ml-1">
            <img
                :src="logo"
                alt=""
                :width="isPhone ? 32 : 40"
                :height="isPhone ? 32 : 40"/>
            <span>KSO</span>
        </router-link>

        <v-spacer></v-spacer>

        <div
            class="ml-auto my-auto d-flex">
            <TopBarMenu
                class="my-auto"/>
        </div>
    </v-system-bar>
</template>

<style scoped>
.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgb(var(--v-theme-on-appbar));
    text-decoration: none;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: .04em;
}

</style>
