<script setup lang="ts">
import {Deployment} from "@/core/services/Deploy/models";
import {useDeploymentActions} from "@/composables/useDeploymentActions";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/**
 * Taking the deployment down in the cluster, away from Deploy in the toolbar and asked once
 * more before it happens.
 */
const props = defineProps<{
    deployment: Deployment
}>();

const {terminate} = useDeploymentActions();
</script>

<template>
    <page-section
        title="Terminate"
        hide-save>
        <div class="danger">
            <div>
                <div class="danger-title">Terminate this deployment</div>
                <div class="danger-text">
                    Every step is taken down in the cluster. The deployment stays in kso as
                    Inactive until it is deployed again.
                </div>
            </div>
            <v-btn
                color="error"
                variant="tonal"
                prepend-icon="fa fa-skull"
                @click="terminate(props.deployment)">
                Terminate
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
