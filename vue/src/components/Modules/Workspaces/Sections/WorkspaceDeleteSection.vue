<script setup lang="ts">
import {ref} from 'vue'
import {useRouter} from "vue-router";
import {Workspace} from "@/core/services/Deploy/models";
import {useWorkspaceActions} from "@/composables/useWorkspaceActions";
import {goBack} from "@/helpers/goBack";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/**
 * Deleting, away from everything used every day, and asked once more before it happens. Only a
 * workspace without deployments can go.
 */
const props = defineProps<{
    workspace: Workspace
}>();

const router = useRouter();
const {remove} = useWorkspaceActions();
const isBusy = ref(false);

function onDeleteBtnClicked() {
    remove(props.workspace, {
        onBusy: busy => isBusy.value = busy,
        onDeleted: () => goBack(router, {name: 'WorkspacesOverview'}),
    });
}
</script>

<template>
    <page-section
        title="Delete"
        hide-save>
        <div class="danger">
            <div>
                <div class="danger-title">Delete this workspace</div>
                <div class="danger-text">
                    Its deployments have to be terminated and deleted first.
                </div>
            </div>
            <v-btn
                color="error"
                variant="tonal"
                prepend-icon="fa fa-trash"
                :loading="isBusy"
                @click="onDeleteBtnClicked">
                Delete
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
</style>
