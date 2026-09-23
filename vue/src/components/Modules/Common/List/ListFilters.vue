<script setup lang="ts">
import {useDisplay} from "vuetify";

/**
 * A list's filters, beside its search where there is room, and behind a button on a phone,
 * where two chip selects beside a search field leave room for none of them. The button says
 * how many are narrowing the list, so a filtered list does not pass for all of it.
 */
const props = defineProps<{
    /** How many filters narrow the list right now. */
    activeCount: number;
}>();

const {xs: isPhone} = useDisplay();
</script>

<template>
    <v-menu
        v-if="isPhone"
        :close-on-content-click="false"
        location="bottom end">
        <template v-slot:activator="{ props: menuProps }">
            <v-btn
                v-bind="menuProps"
                icon
                variant="text"
                aria-label="Filters">
                <v-badge
                    v-if="props.activeCount"
                    color="secondary"
                    :content="props.activeCount">
                    <v-icon size="18">fa fa-filter</v-icon>
                </v-badge>
                <v-icon v-else size="18">fa fa-filter</v-icon>
            </v-btn>
        </template>
        <v-card class="filters-menu">
            <div class="d-flex flex-column ga-3 pa-3">
                <slot/>
            </div>
        </v-card>
    </v-menu>
    <slot v-else/>
</template>

<style scoped>
.filters-menu {
    width: calc(100vw - 32px);
    max-width: 360px;
}
</style>
