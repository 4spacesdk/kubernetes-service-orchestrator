<script setup lang="ts">
import {computed} from 'vue'
import {Workspace} from "@/core/services/Deploy/models";
import {withoutScheme, workspaceUrls} from "./workspaceUrls";

/**
 * Where a workspace answers, one line a deployment, for its page. The first few are shown;
 * the rest are one click away, so a workspace of many deployments does not make the card the
 * tallest thing on the page.
 */
const props = defineProps<{
    workspace: Workspace;
}>();

const MaxShown = 8;

const rows = computed(() => workspaceUrls(props.workspace));
const shown = computed(() => rows.value.slice(0, MaxShown));
const rest = computed(() => rows.value.slice(MaxShown));
</script>

<template>
    <div
        v-if="!rows.length"
        class="text-medium-emphasis">
        Nothing of it is reachable from outside.
    </div>
    <ul
        v-else
        class="addresses">
        <li
            v-for="row in shown"
            :key="row.deployment.id">
            <a
                :href="row.url"
                target="_blank"
                rel="noopener"
                class="text-truncate">{{ withoutScheme(row.url) }}</a>
            <span class="deployment">{{ row.deployment.name }}</span>
        </li>
        <li v-if="rest.length">
            <v-menu location="bottom start">
                <template v-slot:activator="{ props: menuProps }">
                    <a
                        href="#"
                        class="more"
                        v-bind="menuProps"
                        @click.prevent>+{{ rest.length }} more</a>
                </template>
                <v-list density="compact">
                    <v-list-item
                        v-for="row in rest"
                        :key="row.deployment.id"
                        :href="row.url"
                        target="_blank"
                        :title="withoutScheme(row.url)"
                        :subtitle="row.deployment.name"/>
                </v-list>
            </v-menu>
        </li>
    </ul>
</template>

<style scoped>
.addresses {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.addresses li {
    display: flex;
    align-items: baseline;
    gap: 8px;
    min-width: 0;
}

.addresses a {
    color: rgb(var(--v-theme-secondary));
    text-decoration: none;
    min-width: 0;
}

.addresses a:hover {
    text-decoration: underline;
}

.deployment {
    margin-left: auto;
    flex-shrink: 0;
    font-size: 11px;
    color: rgba(var(--v-theme-on-background), 0.5);
}

.more {
    font-size: 12px;
}
</style>
