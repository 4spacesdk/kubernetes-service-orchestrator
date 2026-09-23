<script setup lang="ts">
import {computed, onMounted, onUnmounted, ref} from 'vue'
import {Project} from "@/core/services/Deploy/models";
import {ReferenceData} from "@/core/referenceData";
import AuthService from "@/services/AuthService";
import {RbacPermissions} from "@/constants";
import bus from "@/plugins/bus";
import OverviewCard from "@/components/Pages/Overview/OverviewCard.vue";

/**
 * Workspaces by project: the user's own first - they are in the menu too - then every other.
 * A project is relevance, not access, so every project is here for everybody.
 */
const projects = ref<Project[]>([]);

const myProjectIds = computed(() => (AuthService.currentAuthUser?.projects ?? []).map(project => project.id));
const mine = computed(() => projects.value.filter(project => myProjectIds.value.includes(project.id)));
const others = computed(() => projects.value.filter(project => !myProjectIds.value.includes(project.id)));

const canManage = computed(() => AuthService.currentAuthUser?.hasPermission(RbacPermissions.Developer) ?? false);

onMounted(() => {
    load();
    bus.on('projectSaved', load);
});

onUnmounted(() => {
    bus.off('projectSaved', load);
});

function load() {
    ReferenceData.projects().then(items => projects.value = items);
}
</script>

<template>
    <div class="h-100 content-wrapper">
        <v-toolbar
            density="compact"
            flat
            color="toolbar"
            dark
        >
            <v-toolbar-title>Workspaces</v-toolbar-title>
            <v-btn
                v-if="canManage"
                to="/setup/projects"
                variant="text"
                prepend-icon="fa fa-gear"
                class="text-none">
                Manage projects
            </v-btn>
        </v-toolbar>

        <div class="overview">
            <OverviewCard
                to="/workspaces/all"
                icon="fa fa-layer-group"
                title="All workspaces"
                description="Every workspace, in any project or none"/>
            <OverviewCard
                to="/workspaces/projects/none"
                icon="fa fa-folder-open"
                title="No project"
                description="Workspaces not in any project yet"/>
        </div>

        <template v-if="mine.length">
            <div class="overview-heading">My projects</div>
            <div class="overview">
                <OverviewCard
                    v-for="project in mine"
                    :key="project.id"
                    :to="`/workspaces/projects/${project.id}`"
                    icon="fa fa-folder"
                    :title="project.name ?? ''"
                    :description="project.description"/>
            </div>
        </template>

        <template v-if="others.length">
            <div class="overview-heading">{{ mine.length ? 'Other projects' : 'Projects' }}</div>
            <div class="overview">
                <OverviewCard
                    v-for="project in others"
                    :key="project.id"
                    :to="`/workspaces/projects/${project.id}`"
                    icon="fa fa-folder"
                    :title="project.name ?? ''"
                    :description="project.description"/>
            </div>
        </template>
    </div>
</template>

<style scoped>
.overview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 12px;
    padding: 16px;
}

.overview-heading {
    padding: 8px 16px 0;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-background), 0.6);
}
</style>
