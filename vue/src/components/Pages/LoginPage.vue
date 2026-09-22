<script setup lang="ts">

import {onMounted, ref} from "vue";
import AuthService from "@/services/AuthService";
import {useRoute, useRouter} from "vue-router";

/**
 * Where the visitor was going when the router sent them here. The OAuth round trip comes
 * back to this page without it, so it is kept in the tab's session storage in between.
 */
const DestinationKey = 'login-destination';

const router = useRouter();

/**
 * The code from the sign-in could not be exchanged for a token. The page stops there: signing
 * in again at once would come straight back with a new code - the session on the server is
 * still there - and fail the same way, reloading the tab for ever.
 */
const exchangeFailed = ref(false);

onMounted(() => {
    const route = useRoute();

    // Check for code
    if (route.query.code) {
        // Only a code this tab asked for. One that arrives with another state - or none - was
        // not started here, and is not exchanged.
        if (!AuthService.isOwnLoginState(route.query.state)) {
            exchangeFailed.value = true;
            return;
        }
        AuthService.exchangeCodeForAccessToken(route.query.code as string, succeeded => {
            if (succeeded) {
                checkLoggedIn();
            } else {
                exchangeFailed.value = true;
            }
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
                <p
                    v-if="exchangeFailed"
                    class="mb-4">
                    The sign-in could not be completed. Try again, and if it keeps failing, look at kso's log.
                </p>
                <v-btn
                    @click="login">Login
                </v-btn>
            </v-card-text>
        </v-card>
    </div>
</template>

<style scoped>

</style>
