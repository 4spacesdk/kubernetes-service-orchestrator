<script setup lang="ts" generic="T">
import {computed} from 'vue'
import {
    groupSections,
    isSectionEnabled,
    type DetailSection
} from "@/components/Modules/Common/DetailPage/detailSections";

/**
 * Straight to a section of a thing's page, for a settings menu in a list or a dialog. The
 * section the page opens on is left out: the name is the link to that.
 */
const props = defineProps<{
    item: T;
    id: number;
    sections: DetailSection<T>[];
    routeName: string;
    groupTitle?: (group: string, item: T) => string;
}>();

const groups = computed(() => groupSections(props.sections, props.item)
    .map(group => ({...group, sections: group.sections.filter(section => section.key)}))
    .filter(group => group.sections.length));

function sectionRoute(section: DetailSection<T>) {
    return {
        name: props.routeName,
        params: {id: props.id, section: section.key},
    };
}

function titleOf(group: string) {
    return props.groupTitle ? props.groupTitle(group, props.item) : group;
}

</script>

<template>
    <div class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">

            <v-list
                class="list-items">
                <template
                    v-for="group in groups"
                    :key="group.group">
                    <v-list-subheader v-if="group.group">{{ titleOf(group.group) }}</v-list-subheader>
                    <v-tooltip
                        v-for="section in group.sections"
                        :key="section.key"
                        :disabled="isSectionEnabled(section, props.item)"
                        location="left">
                        <template v-slot:activator="{ props: tooltipProps }">
                            <div v-bind="tooltipProps">
                                <v-list-item
                                    dense
                                    :to="sectionRoute(section)"
                                    :disabled="!isSectionEnabled(section, props.item)">
                                    <v-list-item-title>
                                        <v-icon size="small" class="my-auto ml-2">{{ section.icon }}</v-icon>
                                        <span class="ml-2">{{ section.title }}</span>
                                    </v-list-item-title>
                                </v-list-item>
                            </div>
                        </template>
                        {{ section.disabledHint }}
                    </v-tooltip>
                </template>
            </v-list>
        </v-card>
    </div>
</template>

<style scoped>
.list-wrapper {
    min-width: 120px;
}

.v-list-item {
    min-height: unset;
}

.v-list-item-title {
    font-size: 11px !important;
}
</style>
