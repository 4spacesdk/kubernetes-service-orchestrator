<script setup lang="ts">
import { onMounted, ref } from "vue";
import { ContainerImage } from "@/core/services/Deploy/models";
import type { DialogEventsInterface } from "@/components/Dialogs/DialogEventsInterface";
import DeploymentList from "@/components/Modules/Setup/Deployments/List/DeploymentList.vue";

/**
 * The deployments running an image - the ones its count in the list stands for.
 */
export interface ContainerImageDeploymentsDialog_Input {
    containerImage: ContainerImage;
}

const props = defineProps<{ input: ContainerImageDeploymentsDialog_Input; events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    showDialog.value = true;
});

function onCloseBtnClicked() {
    showDialog.value = false;
    props.events.onClose();
}
</script>

<template>
    <v-dialog persistent height="80vh" width="70vw" min-width="720" v-model="showDialog">
        <v-card class="w-100 h-100">
            <v-card-title>Running deployments · {{ props.input.containerImage.name }}</v-card-title>
            <v-divider />
            <v-card-text>
                <DeploymentList
                    :show-header="false"
                    :filter-by-ids="props.input.containerImage.running_deployment_ids ?? []"
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
