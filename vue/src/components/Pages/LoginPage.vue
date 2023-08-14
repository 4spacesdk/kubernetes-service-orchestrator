<script setup lang="ts">

import {onMounted} from "vue";
import AuthService from "@/services/AuthService";
import {useRoute} from "vue-router";

onMounted(() => {
    const route = useRoute();
    AuthService.checkForAccessTokenInUrl(route.hash);

    if (AuthService.isLoggedIn()) {
        window.location.href = "/app/";
    } else {
        login();
    }
});

function login() {
    AuthService.handleLogin();
}

</script>

<template>
    <div class="d-flex h-100">
        <v-card
            class="ma-auto px-12 py-4"
            color="primary">
            <v-card-title>Login</v-card-title>
            <v-card-text>
                <v-btn
                    @click="login">Login
                </v-btn>
            </v-card-text>
        </v-card>
    </div>
</template>

<style scoped>

</style>
