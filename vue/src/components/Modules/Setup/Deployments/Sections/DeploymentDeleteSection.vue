<script setup lang="ts">
import {useRouter} from "vue-router";
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {goBack} from "@/helpers/goBack";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/**
 * Deleting, away from everything used every day, and asked once more before it happens.
 */
const props = defineProps<{
    deployment: Deployment
}>();

const router = useRouter();

function onDeleteBtnClicked() {
    bus.emit('confirm', {
        body: `Do you want to delete "${props.deployment.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'red',
        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.deployments().deleteById(props.deployment.id!).delete(() => {
                    bus.emit('deploymentSaved');
                    goBack(router, {name: 'Deployments'});
                });
            }
        }
    });
}
</script>

<template>
    <page-section
        title="Delete"
        hide-save>
        <div class="danger">
            <div>
                <div class="danger-title">Delete this deployment</div>
                <div class="danger-text">
                    It is removed from kso only. What it runs in the cluster stays: terminate it
                    under Resources first to take that down.
                </div>
            </div>
            <v-btn
                color="red"
                variant="tonal"
                prepend-icon="fa fa-trash"
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
    color: rgba(0, 0, 0, 0.6);
}
</style>
