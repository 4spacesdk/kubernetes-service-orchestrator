<script setup lang="ts">
import {useRegisterSW} from 'virtual:pwa-register/vue'
import {computed, ref, watch} from "vue";
import {useRouter} from "vue-router";

/**
 * The one place the service worker is registered. It looks for a new version every minute, and
 * says so here when there is one - `main.ts` registered it a second time for the minute check.
 *
 * The worker only knows that the files changed. What changed comes from `release.json`, which
 * the Docker build writes next to them from the CHANGELOG. A build without one - development -
 * says only that a new version is ready.
 *
 * Reloading with unsaved changes is asked about by the page itself (`useUnsavedChanges`), so
 * Reload goes straight ahead. Later hides the prompt until the next page.
 */
const {
    needRefresh,
    updateServiceWorker,
} = useRegisterSW({
    onRegistered(registration) {
        registration && setInterval(() => registration.update(), 60 * 1000);
    },
});

interface Release {
    version: string;
    notes: { section: string | null; text: string }[];
    more: number;
}

const release = ref<Release | null>(null);
const postponed = ref(false);
const isReloading = ref(false);

const show = computed(() => needRefresh.value && !postponed.value);

/** A development build's version is a placeholder, or `latest`, and not worth naming. */
const version = computed(() => {
    const value = release.value?.version ?? '';
    return /^v?\d/.test(value) ? value.replace(/^v/, '') : null;
});

const releaseUrl = computed(() => version.value
    ? `https://github.com/4spacesdk/kubernetes-service-orchestrator/releases/tag/${version.value}`
    : null);

watch(needRefresh, (isNeeded) => {
    if (isNeeded) {
        loadRelease();
    }
});

// Asked again on the next page, where a reload is least in the way.
useRouter().afterEach((to, from) => {
    if (to.path !== from.path) {
        postponed.value = false;
    }
});

function loadRelease() {
    // Past the worker's cache: this is the new version's file, not the one this page came with.
    fetch(`${import.meta.env.BASE_URL}release.json?at=${Date.now()}`, {cache: 'no-store'})
        .then(response => response.ok ? response.json() : null)
        .then((body: Release | null) => release.value = body?.version !== undefined ? body : null)
        .catch(() => release.value = null);
}

function later() {
    postponed.value = true;
}

function reload() {
    isReloading.value = true;
    updateServiceWorker(true);
    setTimeout(() => window.location.reload(), 1500);
}

</script>

<template>
    <v-slide-y-reverse-transition>
        <v-card
            v-if="show"
            class="update-prompt"
            elevation="8"
            rounded="lg"
            role="alert"
        >
            <div class="d-flex align-start ga-3 pa-4 pb-2">
                <v-avatar color="primary" variant="tonal" size="36">
                    <v-icon size="small" icon="fa fa-rocket"/>
                </v-avatar>
                <div class="flex-grow-1 min-width-0">
                    <div class="text-subtitle-1 font-weight-medium">
                        <template v-if="version">kso {{ version }} is ready</template>
                        <template v-else>A new version of kso is ready</template>
                    </div>
                    <div class="text-body-small text-medium-emphasis">
                        Reload to start using it.
                    </div>
                </div>
            </div>

            <div v-if="release?.notes.length" class="px-4 pb-1">
                <div class="text-overline text-medium-emphasis">What's new</div>
                <ul class="notes">
                    <li v-for="(note, index) in release.notes" :key="index" class="text-body-small">
                        {{ note.text }}
                    </li>
                </ul>
                <a
                    v-if="releaseUrl"
                    :href="releaseUrl"
                    target="_blank"
                    rel="noopener"
                    class="text-body-small text-primary"
                >
                    <template v-if="release.more">and {{ release.more }} more on GitHub</template>
                    <template v-else>Read on GitHub</template>
                    <v-icon size="x-small" icon="fa fa-arrow-up-right-from-square" class="ms-1"/>
                </a>
            </div>

            <v-card-actions class="px-4 pb-3">
                <v-spacer/>
                <v-btn variant="text" @click="later()">Later</v-btn>
                <v-btn
                    variant="flat"
                    color="primary"
                    prepend-icon="fa fa-rotate-right"
                    :loading="isReloading"
                    @click="reload()"
                >
                    Reload
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-slide-y-reverse-transition>
</template>

<style scoped>
/* Above the status bar, clear of the menu on a phone. */
.update-prompt {
    position: fixed;
    right: 16px;
    bottom: 40px;
    width: 400px;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - 96px);
    overflow-y: auto;
    z-index: 2400;
}

@media (max-width: 599px) {
    .update-prompt {
        bottom: 72px;
    }
}

.min-width-0 {
    min-width: 0;
}

.notes {
    margin: 0 0 8px;
    padding-left: 18px;
}

.notes li + li {
    margin-top: 4px;
}
</style>
