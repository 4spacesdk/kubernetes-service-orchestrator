<script setup lang="ts">
import {RouterView} from 'vue-router'
import DialogLoader from '@/components/Dialogs/DialogLoader.vue';
import Menu from '@/components/Shell/Menu/Menu.vue';
import AppUpdateReloadPrompt from '@/components/Shell/AppUpdateReloadPrompt.vue';
import TopBar from '@/components/Shell/TopBar/TopBar.vue';
import {onMounted, ref} from "vue";
import AuthService from "@/services/AuthService";
import StatusBar from "@/components/Shell/StatusBar/StatusBar.vue";
import BottomMenu from "@/components/Shell/Menu/BottomMenu.vue";
import {useDisplay} from "vuetify";

const showTopBar = ref(false);
const showMenu = ref(false);

/** A phone: the menu along the bottom instead of down the side, and no status bar under it. */
const {xs: isPhone} = useDisplay();

onMounted(() => {
    showTopBar.value = AuthService.isLoggedIn();
    showMenu.value = AuthService.isLoggedIn();
})

</script>

<template>
    <v-app>
        <TopBar
            v-if="showTopBar"
        />
        <Menu
            v-if="showMenu && !isPhone"
        />
        <BottomMenu
            v-if="showMenu && isPhone"
        />
        <v-main class="wrapper">
            <div class="pa-0 h-100">
                <router-view/>
            </div>
        </v-main>
        <StatusBar v-if="!isPhone"/>

        <!-- Modals -->
        <AppUpdateReloadPrompt/>
        <DialogLoader/>

    </v-app>
</template>

<style scoped>

</style>
