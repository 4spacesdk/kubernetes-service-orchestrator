<script setup lang="ts">
import {computed, onMounted, onUnmounted, ref} from 'vue'
import {Project} from "@/core/services/Deploy/models";
import {ReferenceData} from "@/core/referenceData";
import AuthService from "@/services/AuthService";
import {RbacPermissions, WorkspaceStatusTypes} from "@/constants";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import OverviewCard from "@/components/Pages/Overview/OverviewCard.vue";
import {itemBadge, useMenuBadges} from "@/components/Shell/Menu/menuBadges";

/**
 * Workspaces by project: the user's own first - they are in the menu too - then every other.
 * A project is relevance, not access, so every project is here for everybody.
 */
const projects = ref<Project[]>([]);

const myProjectIds = computed(() => (AuthService.currentAuthUser?.projects ?? []).map(project => project.id));
const mine = computed(() => projects.value.filter(project => myProjectIds.value.includes(project.id)));
const others = computed(() => projects.value.filter(project => !myProjectIds.value.includes(project.id)));

// How many are Degraded, on each card - the same numbers as the menu.
useMenuBadges();

/**
 * How many workspaces each card lists, by the card's url - counted as the list shows them when
 * it opens: Synced and Out of sync.
 */
const counts = ref<Record<string, number>>({});

const canManage = computed(() => AuthService.currentAuthUser?.hasPermission(RbacPermissions.Developer) ?? false);

onMounted(() => {
    load();
    bus.on('projectSaved', load);
    bus.on('workspaceSaved', load);
});

onUnmounted(() => {
    bus.off('projectSaved', load);
    bus.off('workspaceSaved', load);
});

function load() {
    ReferenceData.projects().then(items => {
        projects.value = items;
        count('/workspaces/all');
        count('/workspaces/projects/none', 'none');
        items.forEach(project => count(`/workspaces/projects/${project.id}`, String(project.id)));
    });
}

function count(url: string, project?: string) {
    const api = Api.workspaces().get()
        .whereIn('status', [WorkspaceStatusTypes.OutOfSync, WorkspaceStatusTypes.Synced]);
    if (project !== undefined) {
        api.whereIn('project', [project]);
    }
    api.count(value => counts.value[url] = value);
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
                description="Every workspace, in any project or none"
                :badge="itemBadge('/workspaces/all')"
                :count="counts['/workspaces/all']"/>
            <OverviewCard
                to="/workspaces/projects/none"
                icon="fa fa-folder-open"
                title="No project"
                description="Workspaces not in any project yet"
                :badge="itemBadge('/workspaces/projects/none')"
                :count="counts['/workspaces/projects/none']"/>
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
                    :description="project.description"
                    :badge="itemBadge(`/workspaces/projects/${project.id}`)"
                    :count="counts[`/workspaces/projects/${project.id}`]"/>
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
                    :description="project.description"
                    :badge="itemBadge(`/workspaces/projects/${project.id}`)"
                    :count="counts[`/workspaces/projects/${project.id}`]"/>
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
