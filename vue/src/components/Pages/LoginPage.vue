<script setup lang="ts">

import {onMounted} from "vue";
import AuthService from "@/services/AuthService";
import {useRoute} from "vue-router";

onMounted(() => {
    const route = useRoute();

    // Check for code
    if (route.query.code) {
        AuthService.exchangeCodeForAccessToken(route.query.code as string, () => {
            checkLoggedIn();
        });
        return;
    }

    // Check for access token
    AuthService.checkForAccessTokenInUrl(route.hash);

    checkLoggedIn();
});

function checkLoggedIn() {
    if (AuthService.isLoggedIn()) {
        window.location.href = "/app/";
    } else {
        login();
    }
}

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
