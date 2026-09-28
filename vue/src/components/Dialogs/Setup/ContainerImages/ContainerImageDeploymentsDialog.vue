<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { ContainerImage } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import DeploymentList from "@/components/Modules/Setup/Deployments/List/DeploymentList.vue";

/**
 * The deployments running an image - the ones its count in the list stands for - narrowed to
 * a version when they run more than one.
 */
export interface ContainerImageDeploymentsDialog_Input {
    containerImage: ContainerImage;
}

const props = defineProps<{ input: ContainerImageDeploymentsDialog_Input; events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const deploymentIds = computed(() => props.input.containerImage.running_deployment_ids ?? []);

/** How many of the deployments run each version. Read when the dialog opens. */
const versionCounts = ref<Record<string, number>>({});
const isLoadingVersions = ref(false);
const selectedVersions = ref<string[]>([]);

/**
 * Newest first by semver; a tag that is no version - `develop`, `latest` - after them, by name.
 * The search box would not do: it matches a part, so 1.9.1 would also find 1.9.10.
 */
const versionOptions = computed(() => Object.keys(versionCounts.value)
    .sort(compareVersionsNewestFirst)
    .map(version => ({ value: version, title: `${version} (${versionCounts.value[version]})` })));

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;
    loadVersions();
});

function loadVersions() {
    if (!deploymentIds.value.length) {
        return;
    }
    isLoadingVersions.value = true;
    Api.deployments().get()
        .whereIn('id', deploymentIds.value)
        .limit(deploymentIds.value.length)
        .find(deployments => {
            const counts: Record<string, number> = {};
            for (const deployment of deployments) {
                if (deployment.version) {
                    counts[deployment.version] = (counts[deployment.version] ?? 0) + 1;
                }
            }
            versionCounts.value = counts;
            isLoadingVersions.value = false;
        });
}

function compareVersionsNewestFirst(a: string, b: string): number {
    const parse = (version: string) => version.match(/^v?(\d+)\.(\d+)\.(\d+)(.*)$/)?.slice(1);
    const pa = parse(a);
    const pb = parse(b);
    if (pa && pb) {
        for (let i = 0; i < 3; i++) {
            if (Number(pa[i]) !== Number(pb[i])) {
                return Number(pb[i]) - Number(pa[i]);
            }
        }
        // 1.9.3 is newer than 1.9.3-rc1.
        if (!pa[3] !== !pb[3]) {
            return pa[3] ? 1 : -1;
        }
        return pb[3].localeCompare(pa[3]);
    }
    if (pa || pb) {
        return pa ? -1 : 1;
    }
    return a.localeCompare(b);
}

function onCloseBtnClicked() {
    showDialog.value = false;
    props.events.onClose();
}
</script>

<template>
    <v-dialog persistent height="80vh" width="70vw" min-width="720" v-model="showDialog">
        <v-card class="w-100 h-100">
            <v-card-title class="d-flex align-center flex-wrap ga-2">
                <span class="mr-auto">Running deployments · {{ props.input.containerImage.name }}</span>
                <!-- One version everywhere leaves nothing to choose between. -->
                <v-select
                    v-if="versionOptions.length > 1"
                    v-model="selectedVersions"
                    :items="versionOptions"
                    :loading="isLoadingVersions"
                    label="Version"
                    density="compact"
                    variant="outlined"
                    multiple
                    chips
                    closable-chips
                    clearable
                    hide-details
                    max-width="360"
                    class="version-select"
                />
            </v-card-title>
            <v-divider />
            <v-card-text>
                <DeploymentList
                    :show-header="false"
                    :filter-by-ids="deploymentIds"
                    :filter-by-versions="selectedVersions"
                />
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" @click="onCloseBtnClicked"> Close </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.version-select {
    min-width: 220px;
}
</style>
