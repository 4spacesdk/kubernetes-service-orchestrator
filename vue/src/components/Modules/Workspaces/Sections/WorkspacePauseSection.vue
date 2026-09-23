<script setup lang="ts">
import {Workspace} from "@/core/services/Deploy/models";
import {useWorkspaceActions} from "@/composables/useWorkspaceActions";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/**
 * Shutting the workspace down and keeping it down: auto update leaves a paused workspace alone
 * until the pause is taken off.
 */
const props = defineProps<{
    workspace: Workspace
}>();

const {pause, resume} = useWorkspaceActions();
</script>

<template>
    <page-section
        :title="props.workspace.is_paused ? 'Resume' : 'Pause'"
        hide-save>
        <div
            v-if="props.workspace.is_paused"
            class="danger resume">
            <div>
                <div class="danger-title">This workspace is paused</div>
                <div class="danger-text">
                    Resuming takes the pause off. Deploy brings the workspace back afterwards.
                </div>
            </div>
            <v-btn
                variant="tonal"
                prepend-icon="fa fa-play"
                @click="resume(props.workspace)">
                Resume
            </v-btn>
        </div>
        <div
            v-else
            class="danger">
            <div>
                <div class="danger-title">Pause this workspace</div>
                <div class="danger-text">
                    It is shut down like Terminate, and its disks go with it unless their reclaim
                    policy keeps them. The pause stays until someone takes it off.
                </div>
            </div>
            <v-btn
                color="error"
                variant="tonal"
                prepend-icon="fa fa-pause"
                @click="pause(props.workspace)">
                Pause
            </v-btn>
        </div>
    </page-section>
</template>

<style scoped>
.danger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    max-width: 720px;
    padding: 16px;
    border: 1px solid rgba(var(--v-theme-error), 0.4);
    border-radius: 8px;
}

.danger-title {
    font-weight: 500;
}

.danger-text {
    margin-top: 2px;
    font-size: 12px;
    color: rgba(var(--v-theme-on-background), 0.6);
}

.resume {
    border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
