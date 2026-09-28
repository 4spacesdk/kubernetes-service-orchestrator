<script setup lang="ts">
import { dnsLabelRule } from "@/core/kubernetesNames";
import {computed, onMounted, ref, shallowRef} from 'vue'
import {useDisplay} from "vuetify";
import {Deployment, DeploymentSpecification, Workspace} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {ImagePullPolicies, WorkloadTypes} from "@/constants";
import {useDeploymentActions} from "@/composables/useDeploymentActions";
import {deploymentSections, type DeploymentSection} from "@/components/Modules/Setup/Deployments/Sections/sections";
import {isSectionEnabled} from "@/components/Modules/Common/DetailPage/detailSections";

export interface DeploymentCreateDialog_Input {
    spec: DeploymentSpecification;
    workspace?: Workspace;

    onSavedCallback?: (deployment: Deployment) => void;
}

/**
 * Creating a deployment, as a wizard. The first step is what it always was - name, namespace,
 * version - and creates the deployment as a Draft. The steps after it are the settings its
 * specification gives it, the very sections of its page and in the order of its side menu, so
 * nothing that has to be decided is found only afterwards: Update Management in particular,
 * which asks for on or off instead of being left as it is.
 *
 * A Draft is not rolled out, so every step saves as it is left. The last one deploys it or
 * leaves it a draft. Closed halfway, the draft stays with what was saved.
 */
const props = defineProps<{ input: DeploymentCreateDialog_Input, events: DialogEventsInterface }>();

const {xs: isPhone} = useDisplay();
const {deploy} = useDeploymentActions();

const used = ref(false);
const showDialog = ref(false);

const item = ref<Deployment>(new Deployment());
const isSaving = ref(false);

const workspaceItems = ref<Workspace[]>();
const isLoadingWorkspaces = ref(false);

const showTags = ref(false);
const tags = ref<string[]>([]);
const isLoadingTags = ref(false);

const imagePullPolicies = ref([
    {
        identifier: ImagePullPolicies.IfNotPresent,
        name: "If not present",
    },
    {
        identifier: ImagePullPolicies.Always,
        name: "Always",
    },
    {
        identifier: ImagePullPolicies.Never,
        name: "Never",
    },
]);

/** The draft, once the first step has made it - read with its specification, as the sections need. */
const created = ref<Deployment>();

/** Asked in the first step, so not again. */
const askedFirst = ['version', 'image-pull-policy'];

const steps = computed<DeploymentSection[]>(() => {
    const deployment = created.value;
    if (!deployment) {
        return [];
    }
    return deploymentSections.filter(section =>
        (section.group == 'Settings' || section.group == 'Configuration')
        && !askedFirst.includes(section.key)
        && section.isShown(deployment)
        && isSectionEnabled(section, deployment));
});

/** 0 is the first step, 1..n the sections, n + 1 the summary. */
const step = ref(0);
const isSummary = computed(() => created.value !== undefined && step.value == steps.value.length + 1);
const currentSection = computed(() => step.value >= 1 && step.value <= steps.value.length ? steps.value[step.value - 1] : undefined);
const sectionRef = shallowRef<{ saveNow?: () => Promise<boolean> }>();
const isMovingOn = ref(false);

/** The steps that were saved on the way through, by key - what the summary tells apart from skipped. */
const visited = ref<Record<string, boolean>>({});

/** Read again for the summary: what was actually stored, not what the steps think they sent. */
const summaryDeployment = ref<Deployment>();

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

function render() {
    item.value.name = props.input.spec.name;
    item.value.namespace = props.input.workspace?.namespace ?? '';
    item.value.image = props.input.spec.container_image?.url;
    item.value.image_pull_policy = props.input.spec.container_image?.default_image_pull_policy;
    item.value.workspace_id = props.input.workspace?.id;
    showTags.value = props.input.spec.container_image_id !== undefined;
    showDialog.value = true;

    isLoadingWorkspaces.value = true;
    Api.workspaces().get().find(workspaces => {
        workspaceItems.value = workspaces;
        isLoadingWorkspaces.value = false;
    });

    if (showTags.value) {
        isLoadingTags.value = true;
        Api.deploymentSpecifications().getTagsGetById(props.input.spec.id!)
            .find(response => {
                tags.value = response[0]?.tags ?? [];
                isLoadingTags.value = false;
            });
    }
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

function readDeployment(id: number, next: (deployment: Deployment) => void) {
    Api.deployments().getById(id)
        .include('deployment_specification')
        .include('workspace')
        .find(items => {
            if (items[0]) {
                next(items[0]);
            }
        });
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onWorkspaceChanged() {
    if (item.value.workspace_id) {
        item.value.namespace = workspaceItems.value
            ?.find(workspace => workspace.id === item.value.workspace_id)
            ?.namespace ?? '';
    } else {
        item.value.namespace = '';
    }
}

function onCreateBtnClicked() {
    isSaving.value = true;

    let api;
    if (props.input.workspace !== undefined) {
        api = Api.workspaces().createDeploymentPostById(props.input.workspace.id!);
    } else {
        api = Api.deployments().createPost()
            .workspaceId(item.value.workspace_id!)
    }

    api
        .deploymentSpecificationId(props.input!.spec!.id!)
        .namespace(item.value.namespace!)
        .name(item.value.name!)
        .version(item.value.version!)
        .setErrorHandler(response => {
            if (response.error) {
                bus.emit('toast', {
                    text: response.error
                });
            }
            isSaving.value = false;
            return false;
        });
    api.save(item.value!, newItem => {
        if (newItem) {
            bus.emit('deploymentSaved', newItem);
            if (props.input.onSavedCallback) {
                props.input.onSavedCallback(newItem);
            }
            readDeployment(newItem.id!, deployment => {
                created.value = deployment;
                isSaving.value = false;
                step.value = 1;
            });
        }
    });
}

/** Saves the step being left; stays on it when that is refused, which the step says why. */
async function leaveStep(): Promise<boolean> {
    const section = currentSection.value;
    if (!section) {
        return true;
    }
    isMovingOn.value = true;
    const ok = await (sectionRef.value?.saveNow?.() ?? Promise.resolve(true));
    isMovingOn.value = false;
    if (ok) {
        visited.value[section.key] = true;
    }
    return ok;
}

async function onNextBtnClicked() {
    if (!(await leaveStep())) {
        return;
    }
    step.value++;
    if (isSummary.value) {
        readDeployment(created.value!.id!, deployment => summaryDeployment.value = deployment);
    }
}

async function onBackBtnClicked() {
    if (!(await leaveStep())) {
        return;
    }
    step.value--;
}

async function onStepClicked(index: number) {
    if (!created.value || index < 1 || index == step.value || !(await leaveStep())) {
        return;
    }
    step.value = index;
    if (isSummary.value) {
        readDeployment(created.value.id!, deployment => summaryDeployment.value = deployment);
    }
}

function onDeployBtnClicked() {
    const deployment = created.value!;
    close();
    deploy(deployment);
}

function onLeaveAsDraftBtnClicked() {
    bus.emit('toast', {text: `${created.value!.name} is kept as a draft`});
    close();
}

async function onCloseBtnClicked() {
    if (!created.value) {
        close();
        return;
    }
    // What the step has is kept too, as far as it can be.
    await leaveStep();
    bus.emit('confirm', {
        body: `Close the wizard?\n\n"${created.value.name}" is kept as a draft, with what was saved so far. It can be deleted from the menu on its row, or with Delete draft here.`,
        confirmIcon: 'fa fa-circle-xmark',
        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                close();
            }
        },
    });
}

function onDeleteDraftBtnClicked() {
    const deployment = created.value!;
    bus.emit('confirm', {
        body: `Delete the draft "${deployment.name}"? Nothing of it has reached the cluster.`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'error',
        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.deployments().deleteById(deployment.id!).delete(() => bus.emit('deploymentSaved'));
                close();
            }
        },
    });
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        height="80vh"
        width="70vw"
        :min-width="isPhone ? undefined : 720"
        :fullscreen="isPhone"
        v-model="showDialog">
        <v-card
            class="w-100 h-100 d-flex flex-column">
            <v-card-title>
                <span v-if="props.input.workspace">{{ props.input.workspace.name }} - </span>
                <span>{{ props.input.spec.name }}</span>
            </v-card-title>
            <v-divider/>

            <div class="wizard">
                <!-- The steps, as the side menu of the page the deployment will have. -->
                <nav v-if="!isPhone" class="wizard-steps">
                    <v-list density="compact" nav>
                        <v-list-item
                            :active="step == 0"
                            :disabled="created !== undefined"
                            prepend-icon="fa fa-plus"
                            title="Create"/>
                        <v-list-item
                            v-for="(section, index) in steps"
                            :key="section.key"
                            :active="step == index + 1"
                            :prepend-icon="visited[section.key] ? 'fa fa-check' : section.icon"
                            :title="section.title"
                            @click="onStepClicked(index + 1)"/>
                        <v-list-item
                            v-if="created"
                            :active="isSummary"
                            prepend-icon="fa fa-flag-checkered"
                            title="Summary"
                            @click="onStepClicked(steps.length + 1)"/>
                    </v-list>
                </nav>

                <div class="wizard-content">
                    <div v-if="isPhone && created" class="px-4 pt-3 text-body-medium text-medium-emphasis">
                        Step {{ step + 1 }} of {{ steps.length + 2 }} · {{ isSummary ? 'Summary' : currentSection?.title }}
                    </div>

                    <!-- 1. What it always asked: this creates the draft. -->
                    <v-card-text v-if="step == 0">
                        <v-row>
                            <v-col
                                v-if="props.input.spec.workload_type !== WorkloadTypes.CustomResource"
                                cols="12"
                            >
                                <v-text-field
                                    v-model="item.image"
                                    :disabled="true"
                                    variant="outlined"
                                    label="Image"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col
                                v-if="props.input.spec.workload_type !== WorkloadTypes.CustomResource"
                                cols="6"
                            >
                                <v-checkbox
                                    v-model="props.input.spec.enable_database"
                                    :disabled="true"
                                    label="Database"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col
                                v-if="props.input.spec.workload_type !== WorkloadTypes.CustomResource"
                                cols="6"
                            >
                                <v-checkbox
                                    v-model="props.input.spec.enable_cronjob"
                                    :disabled="true"
                                    label="CronJob"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col
                                v-if="props.input.spec.workload_type !== WorkloadTypes.CustomResource"
                                cols="6"
                            >
                                <v-checkbox
                                    v-model="props.input.spec.enable_external_access"
                                    :disabled="true"
                                    label="External Access"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>
                            <v-col
                                v-if="props.input.spec.workload_type !== WorkloadTypes.CustomResource"
                                cols="6"
                            >
                                <v-checkbox
                                    v-model="props.input.spec.enable_internal_access"
                                    :disabled="true"
                                    label="Internal Access"
                                    density="compact"
                                    hide-details
                                />
                            </v-col>

                            <v-col :cols="isPhone ? 12 : 6">
                                <v-select
                                    v-model="item.workspace_id"
                                    :items="workspaceItems"
                                    :loading="isLoadingWorkspaces"
                                    :disabled="props.input.workspace !== undefined"
                                    item-value="id"
                                    item-title="name"
                                    variant="outlined"
                                    label="Workspace"
                                    clearable
                                    @update:modelValue="onWorkspaceChanged"
                                >
                                    <template v-slot:item="{ props, internalItem: item }">
                                        <v-list-item
                                            v-bind="props"
                                            :subtitle="`Namespace: ${item.raw.namespace}`"
                                        />
                                    </template>
                                </v-select>
                            </v-col>
                            <v-col :cols="isPhone ? 12 : 6">
                                <v-text-field
                                    v-model="item.namespace"
                                    :disabled="props.input.workspace !== undefined"
                                    variant="outlined"
                                    :rules="[dnsLabelRule]"
                                    label="Namespace"
                                    clearable
                                    persistent-hint
                                    hint="Max 63 characters: a-z, 0-9 and -"/>
                            </v-col>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="item.name"
                                    variant="outlined"
                                    :rules="[
                                        v => /^[a-z0-9-]{1,61}$/.test(v) || 'Invalid format'
                                    ]"
                                    label="Name"
                                    clearable
                                    persistent-hint
                                    hint="Max 63 characters, lowercase-only"/>
                            </v-col>
                            <v-col
                                v-if="showTags"
                                :cols="isPhone ? 12 : 6"
                            >
                                <v-select
                                    v-model="item.version"
                                    :loading="isLoadingTags"
                                    :items="tags"
                                    variant="outlined"
                                    label="Version"/>
                            </v-col>
                            <v-col
                                v-if="showTags"
                                :cols="isPhone ? 12 : 6"
                            >
                                <v-select
                                    v-model="item.image_pull_policy"
                                    :items="imagePullPolicies"
                                    item-title="name"
                                    item-value="identifier"
                                    variant="outlined"
                                    label="Image Pull Policy"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <!-- 2..n. The deployment's own sections, saving as they go. -->
                    <component
                        v-else-if="currentSection && created"
                        :is="currentSection.component"
                        :key="currentSection.key"
                        ref="sectionRef"
                        :deployment="created"
                        v-bind="currentSection.key == 'update-management' ? {requireChoice: true} : {}"/>

                    <!-- n + 1. What was decided, and what was not. -->
                    <v-card-text v-else-if="isSummary && created">
                        <p class="mb-4">
                            <strong>{{ created.name }}</strong> is a draft in
                            <strong>{{ created.namespace }}</strong>. Nothing has reached the cluster yet.
                        </p>
                        <v-list density="compact" class="summary">
                            <v-list-item
                                v-for="section in steps"
                                :key="section.key"
                                :prepend-icon="visited[section.key] ? 'fa fa-check' : 'fa fa-minus'"
                                :title="section.title"
                                :subtitle="visited[section.key] ? 'Gone through' : 'Skipped - as the specification gives it'"/>
                        </v-list>
                        <p v-if="summaryDeployment" class="mt-4 text-body-medium">
                            <template v-if="summaryDeployment.auto_update_enabled">
                                Updates itself to tags matching <code>{{ summaryDeployment.auto_update_tag_regex }}</code>{{ summaryDeployment.auto_update_require_approval ? ', each approved first' : '' }}.
                            </template>
                            <template v-else>
                                Updated by hand.
                            </template>
                        </p>
                    </v-card-text>
                </div>
            </div>

            <v-divider/>
            <v-card-actions class="flex-wrap ga-2">
                <v-btn
                    v-if="created"
                    variant="text"
                    color="error"
                    prepend-icon="fa fa-trash"
                    @click="onDeleteDraftBtnClicked">
                    Delete draft
                </v-btn>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Close
                </v-btn>

                <v-btn
                    v-if="step == 0"
                    :loading="isSaving"
                    flat
                    variant="tonal"
                    append-icon="fa fa-arrow-right"
                    color="success"
                    @click="onCreateBtnClicked">
                    Create and continue
                </v-btn>

                <template v-else-if="!isSummary">
                    <v-btn
                        v-if="step > 1"
                        variant="tonal"
                        prepend-icon="fa fa-arrow-left"
                        :disabled="isMovingOn"
                        @click="onBackBtnClicked">
                        Back
                    </v-btn>
                    <v-btn
                        flat
                        variant="tonal"
                        color="primary"
                        append-icon="fa fa-arrow-right"
                        :loading="isMovingOn"
                        @click="onNextBtnClicked">
                        Next
                    </v-btn>
                </template>

                <template v-else>
                    <v-btn
                        variant="tonal"
                        prepend-icon="fa fa-arrow-left"
                        @click="onBackBtnClicked">
                        Back
                    </v-btn>
                    <v-btn
                        variant="tonal"
                        prepend-icon="fa fa-file-pen"
                        @click="onLeaveAsDraftBtnClicked">
                        Leave as draft
                    </v-btn>
                    <v-btn
                        flat
                        variant="tonal"
                        color="success"
                        prepend-icon="fa fa-play"
                        @click="onDeployBtnClicked">
                        Deploy now
                    </v-btn>
                </template>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.wizard {
    display: flex;
    /* Whatever the step holds, the title and the buttons stay put and the step scrolls. The
       title and the actions are fixed in every dialog (main.scss), so the room for them is
       left here, as `.v-card-text` has it everywhere else. */
    flex: 1 1 0;
    min-height: 0;
    overflow: hidden;
    padding-top: 64px;
    padding-bottom: 64px;
}

.wizard-steps {
    flex: 0 0 220px;
    border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    overflow-y: auto;
}

.wizard-content {
    flex: 1 1 auto;
    min-width: 0;
    overflow-y: auto;
}

/* The step list already names the section. */
.wizard-content :deep(.page-section-title) {
    visibility: hidden;
}

.summary {
    background: transparent;
}
</style>
