<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Deployment } from "@/core/services/Deploy/models";
import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import ApiService from "@/services/ApiService";

/**
 * Set the version of several deployments at once. Setting a version rolls it out,
 * so this is also how several are deployed together.
 *
 * Each deployment picks its own tag, because their images have different ones; "Set all
 * to" fills in every row that has the tag. They are rolled out one at a time - each is a
 * synchronous deploy against the cluster - and one that fails does not stop the rest.
 */
export interface DeploymentBulkUpdateVersionDialog_Input {
    deployments: Deployment[];
}

const props = defineProps<{ input: DeploymentBulkUpdateVersionDialog_Input; events: DialogEventsInterface }>();

interface Row {
    deployment: Deployment;
    tags: string[];
    version: string;
    state: "idle" | "running" | "ok" | "error" | "skipped";
    message?: string;
}

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);
const isRunning = ref(false);

const rows = ref<Row[]>([]);
const allTo = ref<string | null>(null);
/** Setting the version a deployment already has redeploys it, which restarts its pods. */
const redeployUnchanged = ref(false);

// Natural order, so v2.29.9 comes before v2.29.10.
const allTags = computed(() =>
    [...new Set(rows.value.flatMap(row => row.tags))].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
);
const toRun = computed(() =>
    rows.value.filter(row => row.version && row.state != "ok" && (redeployUnchanged.value || row.version != row.deployment.version))
);
const failed = computed(() => rows.value.filter(row => row.state == "error"));

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;
    render();
});

/**
 * Tags are per specification, so deployments of the same one share a lookup.
 */
function render() {
    rows.value = props.input.deployments.map(deployment => ({
        deployment,
        tags: [],
        version: deployment.version ?? "",
        state: "idle",
    }));

    const specificationIds = [...new Set(props.input.deployments.map(deployment => deployment.deployment_specification_id!))];
    let pending = specificationIds.length;
    isLoading.value = pending > 0;
    for (const specificationId of specificationIds) {
        Api.deploymentSpecifications()
            .getTagsGetById(specificationId)
            .find(response => {
                const tags = response[0]?.tags ?? [];
                rows.value.filter(row => row.deployment.deployment_specification_id == specificationId).forEach(row => (row.tags = tags));
                isLoading.value = --pending > 0;
            });
    }
}

async function run(selection: Row[]) {
    isRunning.value = true;
    for (const row of selection) {
        row.state = "running";
        row.message = undefined;
        try {
            const response = await ApiService.apiAxios!.put(`/deployments/${row.deployment.id}/version`, null, {
                params: { value: row.version },
            });
            if (response.data?.status === "OK") {
                row.state = "ok";
            } else {
                row.state = "error";
                row.message = String(response.data?.error ?? "Failed");
            }
        } catch (e: any) {
            row.state = "error";
            row.message = e?.message ?? "Failed";
        }
    }
    isRunning.value = false;
    bus.emit("deploymentSaved", undefined);
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

/**
 * Only rows that have the tag. The others keep what they had and say why.
 */
function onAllToChanged(tag: string | null) {
    if (!tag) {
        return;
    }
    for (const row of rows.value) {
        if (row.state == "ok") {
            continue;
        }
        if (row.tags.includes(tag)) {
            row.version = tag;
            row.state = "idle";
            row.message = undefined;
        } else {
            row.state = "skipped";
            row.message = `No tag ${tag}`;
        }
    }
}

function onUpdateBtnClicked() {
    run(toRun.value);
}

function onRetryBtnClicked() {
    run(failed.value);
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>
</script>

<template>
    <v-dialog persistent width="60vw" min-width="720" v-model="showDialog">
        <v-card class="w-100" :loading="isLoading">
            <v-card-title>Update version of {{ rows.length }} deployments</v-card-title>
            <v-divider />
            <v-card-text>
                <div class="px-4 pt-4">
                    <v-combobox
                        v-model="allTo"
                        :items="allTags"
                        label="Set all to"
                        hint="Fills in every deployment that has this tag"
                        persistent-hint
                        variant="outlined"
                        density="compact"
                        :disabled="isRunning"
                        @update:model-value="onAllToChanged"
                    />
                    <v-checkbox
                        v-model="redeployUnchanged"
                        label="Also redeploy those that keep their version"
                        density="compact"
                        hide-details
                        :disabled="isRunning"
                    />
                </div>
                <v-table density="compact">
                    <thead>
                        <tr>
                            <th>Deployment</th>
                            <th>Current</th>
                            <th style="width: 35%">New version</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.deployment.id">
                            <td>{{ row.deployment.name }}</td>
                            <td class="text-medium-emphasis">{{ row.deployment.version }}</td>
                            <td>
                                <v-combobox
                                    v-model="row.version"
                                    :items="row.tags"
                                    variant="plain"
                                    density="compact"
                                    hide-details
                                    :disabled="isRunning || row.state == 'ok'"
                                    @update:model-value="row.state = 'idle'; row.message = undefined"
                                />
                            </td>
                            <td class="text-no-wrap">
                                <v-progress-circular v-if="row.state == 'running'" indeterminate size="16" width="2" />
                                <v-icon v-else-if="row.state == 'ok'" color="success" size="small">fa fa-check</v-icon>
                                <span v-else-if="row.message" :class="row.state == 'error' ? 'text-error' : 'text-medium-emphasis'" :title="row.message">
                                    <v-icon size="small">{{ row.state == "error" ? "fa fa-circle-exclamation" : "fa fa-minus" }}</v-icon>
                                    {{ row.message.length > 60 ? row.message.slice(0, 60) + "…" : row.message }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-btn v-if="failed.length && !isRunning" variant="tonal" prepend-icon="fa fa-rotate-right" @click="onRetryBtnClicked">
                    Retry {{ failed.length }} failed
                </v-btn>
                <v-spacer />
                <v-btn variant="tonal" color="grey" prepend-icon="fa fa-circle-xmark" :disabled="isRunning" @click="onCloseBtnClicked"> Close </v-btn>
                <v-btn
                    variant="tonal"
                    color="success"
                    prepend-icon="fa fa-play"
                    :disabled="isLoading || toRun.length == 0"
                    :loading="isRunning"
                    @click="onUpdateBtnClicked">
                    {{ toRun.length ? `Update ${toRun.length}` : "Update" }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped></style>
