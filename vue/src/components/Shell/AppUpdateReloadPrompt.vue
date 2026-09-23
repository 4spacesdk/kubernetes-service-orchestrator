<script setup lang="ts">
import {useRegisterSW} from 'virtual:pwa-register/vue'

/**
 * The one place the service worker is registered. It looks for a new version every minute, and
 * says so here when there is one - `main.ts` registered it a second time for the minute check.
 */
const {
    offlineReady,
    needRefresh,
    updateServiceWorker,
} = useRegisterSW({
    onRegistered(registration) {
        registration && setInterval(() => registration.update(), 60 * 1000);
    },
})

const close = async() => {
    offlineReady.value = false
    needRefresh.value = false
}

function reload() {
    updateServiceWorker(true);
    setTimeout(() => window.location.reload(), 1500);
}

</script>

<template>
    <div
        v-if="offlineReady || needRefresh"
        class="pwa-toast"
        role="alert"
    >
        <div class="message">
            <span v-if="offlineReady">App ready to work offline</span>
            <span v-else>New content available, click on reload button to update..</span>
        </div>
        <button v-if="needRefresh" @click="reload()">
            Reload
        </button>
    </div>
</template>

<style>
.pwa-toast {
    position: fixed;
    right: 0;
    bottom: 0;
    margin: 16px;
    padding: 12px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 4px;
    z-index: 1;
    text-align: left;
    box-shadow: 3px 4px 5px 0 #8885;
    background-color: rgb(var(--v-theme-background));
}

.pwa-toast .message {
    margin-bottom: 8px;
}

.pwa-toast button {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    outline: none;
    margin-right: 5px;
    border-radius: 2px;
    padding: 3px 10px;
}
</style>
