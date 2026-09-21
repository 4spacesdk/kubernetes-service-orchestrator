<script setup lang="ts">

import {onMounted} from "vue";
import AuthService from "@/services/AuthService";
import {useRoute, useRouter} from "vue-router";

/**
 * Where the visitor was going when the router sent them here. The OAuth round trip comes
 * back to this page without it, so it is kept in the tab's session storage in between.
 */
const DestinationKey = 'login-destination';

const router = useRouter();

onMounted(() => {
    const route = useRoute();

    // Check for code
    if (route.query.code) {
        AuthService.exchangeCodeForAccessToken(route.query.code as string, () => {
            checkLoggedIn();
        });
        return;
    }

    rememberDestination(route.query.redirect);

    // Check for access token
    AuthService.checkForAccessTokenInUrl(route.hash);

    checkLoggedIn();
});

/**
 * Only a path within the app. `//host` is not one: the browser reads it as another site.
 */
function rememberDestination(redirect: unknown) {
    try {
        if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')) {
            sessionStorage.setItem(DestinationKey, redirect);
        } else {
            sessionStorage.removeItem(DestinationKey);
        }
    } catch {
        // Storage can be unavailable; the visitor then lands on the front page.
    }
}

function takeDestination(): string {
    try {
        const destination = sessionStorage.getItem(DestinationKey);
        sessionStorage.removeItem(DestinationKey);
        if (destination) {
            return router.resolve(destination).href;
        }
    } catch {
        // As above.
    }
    return "/app/";
}

function checkLoggedIn() {
    if (AuthService.isLoggedIn()) {
        window.location.href = takeDestination();
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
