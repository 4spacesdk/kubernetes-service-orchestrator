<script setup lang="ts">
import {computed, defineComponent, reactive, ref} from 'vue'
import AuthService from "@/services/AuthService";
import bus from "@/plugins/bus";
import {useAppTheme, type ThemeMode} from "@/composables/useAppTheme";

const showMenu = ref(false);
const userFullName = computed(() => AuthService.currentAuthUser?.name);

function onMyProfileBtnClicked() {
    bus.emit('userEdit', {
        user: AuthService.currentAuthUser!
    });
    showMenu.value = false;
}

const {mode} = useAppTheme();

const modes: {value: ThemeMode, title: string, icon: string}[] = [
    {value: 'system', title: 'As the system', icon: 'fa fa-circle-half-stroke'},
    {value: 'light', title: 'Light', icon: 'fa fa-sun'},
    {value: 'dark', title: 'Dark', icon: 'fa fa-moon'},
];

function onLogoutBtnClicked() {
    AuthService.handleLogout();
}

</script>

<template>
    <v-menu
        v-model="showMenu"
        :close-on-content-click="false"
        left
        min-width="250"
        offset-y>
        <template v-slot:activator="{ props }">
            <v-btn v-bind="props"
                   flat
                   class="text-none d-flex p-2"
                   color="transparent"
                   >

                <span class="ml-2 my-auto">{{ userFullName }}</span>
                <v-icon class="my-auto ml-2">fa fa-chevron-down</v-icon>
            </v-btn>
        </template>

        <v-list class="list-items">
            <v-list-item
                density="comfortable"
                @click="onMyProfileBtnClicked">
                <v-list-item-title>
                    <v-icon size="small" class="my-auto ml-2">fa fa-user</v-icon>
                    <span class="ml-2">Profile</span>
                </v-list-item-title>
            </v-list-item>
            <v-divider class="my-1"/>
            <v-list-subheader>Theme</v-list-subheader>
            <v-list-item
                v-for="option in modes"
                :key="option.value"
                density="comfortable"
                :active="mode == option.value"
                color="secondary"
                @click="mode = option.value">
                <v-list-item-title>
                    <v-icon size="small" class="my-auto ml-2">{{ option.icon }}</v-icon>
                    <span class="ml-2">{{ option.title }}</span>
                </v-list-item-title>
            </v-list-item>

            <v-divider class="my-1"/>
            <v-list-item
                density="comfortable"
                @click="onLogoutBtnClicked">
                <v-list-item-title >
                    <v-icon size="small" class="my-auto ml-2">fa fa-lock</v-icon>
                    <span class="ml-2">Sign out</span>
                </v-list-item-title>
            </v-list-item>
        </v-list>
    </v-menu>
</template>

<style scoped>
.v-list-item{
    min-height: unset;
}
.v-list-item-title{
    font-size: 12px!important;
}
</style>
