<script setup lang="ts">
import {RouterView} from 'vue-router'
import DialogLoader from '@/components/Dialogs/DialogLoader.vue';
import Menu from '@/components/Shell/Menu/Menu.vue';
import AppUpdateReloadPrompt from '@/components/Shell/AppUpdateReloadPrompt.vue';
import TopBar from '@/components/Shell/TopBar/TopBar.vue';
import {onMounted, ref} from "vue";
import AuthService from "@/services/AuthService";
import StatusBar from "@/components/Shell/StatusBar/StatusBar.vue";

const showTopBar = ref(false);
const showMenu = ref(false);

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
            v-if="showMenu"
        />
        <v-main class="wrapper">
            <div class="pa-0 h-100">
                <router-view/>
            </div>
        </v-main>
        <StatusBar/>

        <!-- Modals -->
        <AppUpdateReloadPrompt/>
        <DialogLoader/>

    </v-app>
</template>

<style scoped>

</style>
