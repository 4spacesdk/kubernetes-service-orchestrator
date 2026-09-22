<script setup lang="ts">
import {onMounted, ref} from 'vue'
import {Deployment} from "@/core/services/Deploy/models";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import DeploymentResources from "@/components/Modules/Setup/Deployments/Resources/DeploymentResources.vue";
import DetailSectionMenu from "@/components/Modules/Common/DetailPage/DetailSectionMenu.vue";
import {deploymentSections} from "@/components/Modules/Setup/Deployments/Sections/sections";

export interface DeploymentResourceListDialog_Input {
    deployment: Deployment;
}

const props = defineProps<{ input: DeploymentResourceListDialog_Input, events: DialogEventsInterface }>();

const showDialog = ref(false);

onMounted(() => {
    showDialog.value = true;
});

function close() {
    showDialog.value = false;
    props.events.onClose();
}

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        height="65vh"
        v-model="showDialog">
        <v-card
            class="w-100 h-100">
            <v-card-title>
                <div class="d-flex w-100">
                    <span class="my-auto">Resources</span>
                    <v-chip class="my-auto mx-auto">{{
                            props.input.deployment.name
                        }}.{{ props.input.deployment.namespace }}
                    </v-chip>
                </div>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <deployment-resources :deployment="props.input.deployment"/>
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-menu
                    min-width="250">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            variant="plain" color="primary" size="small">
                            <v-icon>fa fa-cog</v-icon>
                            <v-tooltip activator="parent" location="bottom">Settings</v-tooltip>
                        </v-btn>
                    </template>
                    <detail-section-menu
                        :item="props.input.deployment"
                        :id="props.input.deployment.id!"
                        :sections="deploymentSections"
                        route-name="DeploymentById"/>
                </v-menu>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="close">
                    Close
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
