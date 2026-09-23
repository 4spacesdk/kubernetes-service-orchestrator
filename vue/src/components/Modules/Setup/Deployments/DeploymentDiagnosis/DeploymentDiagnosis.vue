<script setup lang="ts">
import {ref} from "vue";
import {useRouter} from "vue-router";
import {Deployment} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import type {DeploymentDiagnosisAction, DeploymentDiagnosisResponse} from "@/core/services/Deploy/Api";
import {DiagnosisVerdicts} from "@/constants";
import bus from "@/plugins/bus";

/**
 * Why the deployment is doing badly, asked for with a click - each rule's finding, how sure it
 * is, what it was read off, and what would fix it. Worked out when asked, never kept.
 */
const props = defineProps<{
    deployment: Deployment
}>();

const router = useRouter();

const isLoading = ref(false);
const isActing = ref(false);
const diagnosis = ref<DeploymentDiagnosisResponse>();
const error = ref<string>();

const looks: Record<string, { title: string; color: string }> = {
    [DiagnosisVerdicts.Certain]: {title: "Certain", color: "error"},
    [DiagnosisVerdicts.Possible]: {title: "Possible", color: "warning"},
    [DiagnosisVerdicts.CannotTell]: {title: "Cannot tell", color: "grey"},
};

function diagnose() {
    isLoading.value = true;
    error.value = undefined;
    const api = Api.deployments().getDiagnosisGetById(props.deployment.id!);
    api.setErrorHandler(response => {
        error.value = response.error ?? 'The diagnosis could not be made';
        isLoading.value = false;
        return false;
    });
    api.find(response => {
        diagnosis.value = response[0];
        isLoading.value = false;
    });
}

function act(action: DeploymentDiagnosisAction) {
    switch (action.type) {
        case 'section':
            router.push({name: 'DeploymentById', params: {id: props.deployment.id, section: action.section}});
            break;
        case 'migration_job':
            router.push({name: 'DeploymentById', params: {id: props.deployment.id, section: 'migration-jobs'}});
            break;
        case 'rollback':
            bus.emit('confirm', {
                body: `Roll ${props.deployment.name} back to ${action.version}?`,
                confirmIcon: 'fa fa-rotate-left',
                responseCallback: (confirmed: boolean) => {
                    if (confirmed) {
                        run(Api.deployments().updateVersionPutById(props.deployment.id!).value(action.version!), 'Rolled back');
                    }
                }
            });
            break;
        case 'deploy':
            bus.emit('confirm', {
                body: `Deploy ${props.deployment.name} again?`,
                confirmIcon: 'fa fa-rocket',
                responseCallback: (confirmed: boolean) => {
                    if (confirmed) {
                        run(Api.deployments().deployPutById(props.deployment.id!), 'Deployed');
                    }
                }
            });
            break;
    }
}

function run(api: any, done: string) {
    isActing.value = true;
    api.setErrorHandler((response: any) => {
        if (response.error) {
            bus.emit('toast', {text: response.error});
        }
        isActing.value = false;
        return false;
    });
    api.save(null, (newItem: Deployment) => {
        bus.emit('deploymentSaved', newItem);
        bus.emit('toast', {text: done});
        isActing.value = false;
        diagnosis.value = undefined;
    });
}
</script>

<template>
    <div class="diagnosis">
        <v-btn
            v-if="!diagnosis"
            size="small"
            variant="tonal"
            prepend-icon="fa fa-stethoscope"
            :loading="isLoading"
            @click="diagnose">
            Why?
        </v-btn>
        <div v-if="error" class="text-error mt-2">{{ error }}</div>

        <template v-if="diagnosis">
            <div class="d-flex align-center">
                <span class="text-medium-emphasis">
                    {{ diagnosis.findings?.length ? 'What kso can see' : 'No rule found a cause kso can point to.' }}
                </span>
                <v-btn
                    class="ml-2"
                    size="x-small"
                    variant="text"
                    icon="fa fa-arrows-rotate"
                    :loading="isLoading"
                    @click="diagnose"/>
            </div>

            <v-card
                v-for="(finding, index) in diagnosis.findings"
                :key="index"
                class="finding mt-2"
                variant="outlined">
                <v-card-text>
                    <div class="d-flex align-center flex-wrap ga-2">
                        <v-chip
                            size="small"
                            label
                            variant="tonal"
                            :color="looks[finding.verdict!]?.color">
                            {{ looks[finding.verdict!]?.title ?? finding.verdict }}
                        </v-chip>
                        <span class="cause">{{ finding.cause }}</span>
                        <v-spacer/>
                        <v-btn
                            v-if="finding.action"
                            size="small"
                            color="primary"
                            variant="tonal"
                            :loading="isActing"
                            @click="act(finding.action)">
                            {{ finding.action.label }}
                        </v-btn>
                    </div>
                    <pre
                        v-if="finding.evidence?.length"
                        class="evidence mt-2">{{ finding.evidence.join('\n') }}</pre>
                </v-card-text>
            </v-card>
        </template>
    </div>
</template>

<style scoped>
.diagnosis {
    max-width: 900px;
}

.cause {
    font-weight: 500;
}

.evidence {
    font-size: 12px;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 240px;
    overflow: auto;
    color: rgba(var(--v-theme-on-surface), 0.7);
}
</style>
