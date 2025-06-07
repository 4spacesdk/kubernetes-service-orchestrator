<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {ContainerImage, K8sCronJob} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {
    ContainerImageTagPolicies,
    CronJobConcurrencyPolicies,
    CronJobRestartPolicies,
    ImagePullPolicies
} from "@/constants";
import {VTextField} from "vuetify/components/VTextField";
import VariableBtn from "@/components/Modules/Common/VariableBtn.vue";

export interface CronJobEditDialog_Input {
    cronJob: K8sCronJob;

    onSaveCallback?: (item: K8sCronJob) => void;
}

interface Argument {
    value: string;
}

const props = defineProps<{ input: CronJobEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const namePrefix = ref('');
const item = ref<K8sCronJob>(new K8sCronJob());
const args = ref<Argument[]>([]);

const concurrencyPolicies = ref([
    {
        identifier: CronJobConcurrencyPolicies.Allow,
        name: "Allow",
    },
    {
        identifier: CronJobConcurrencyPolicies.Forbid,
        name: "Forbid",
    },
    {
        identifier: CronJobConcurrencyPolicies.Replace,
        name: "Replace",
    },
]);
const restartPolicies = ref([
    {
        identifier: CronJobRestartPolicies.Always,
        name: "Always",
    },
    {
        identifier: CronJobRestartPolicies.OnFailure,
        name: "OnFailure",
    },
    {
        identifier: CronJobRestartPolicies.Never,
        name: "Never",
    },
]);

const containerImageItems = ref<ContainerImage[]>([]);
const isLoadingContainerImageItems = ref(false);
const containerImageTagPolicies = ref([
    {
        identifier: ContainerImageTagPolicies.MatchDeployment,
        name: "Match deployment",
    },
    {
        identifier: ContainerImageTagPolicies.Static,
        name: "Static",
    },
    {
        identifier: ContainerImageTagPolicies.Default,
        name: "Default",
    },
]);
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

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

onUnmounted(() => {
});

function render() {
    if (props.input.cronJob.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.k8sCronJobs().getById(props.input.cronJob.id!).find(items => {
            if (!items || items.length == 0) {
                return;
            }
            item.value = items[0];
            isLoading.value = false;
            args.value = JSON.parse(items[0].args ?? '[]')?.map((value: string) => {
                return {
                    value: value
                };
            });
            renderNamePrefix();
        });
    } else {
        item.value = props.input.cronJob;
        showDialog.value = true;
        renderNamePrefix();
    }

    isLoadingContainerImageItems.value = true;
    Api.containerImages().get()
        .orderAsc('name')
        .find(items => {
            containerImageItems.value = items;
            isLoadingContainerImageItems.value = false;
        });
}

function close() {
    showDialog.value = false;
    bus.emit('cronJobEditDialog_closed', item.value);
    props.events.onClose();
}

function renderNamePrefix() {
    namePrefix.value = ((item.value.name?.length ?? 0) > 0) ? '{deployment-name}-' : '{deployment-name}';
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

watch(() => item.value.name, newName => renderNamePrefix());

function onSaveBtnClicked() {
    item.value.args = JSON.stringify(args.value.map(value => value.value));

    const api = item.value!.exists() ? Api.k8sCronJobs().patchById(item.value!.id!) : Api.k8sCronJobs().post();

    api.save(item.value!, newItem => {
        bus.emit('cronJobSaved', newItem);

        if (props.input.onSaveCallback) {
            props.input.onSaveCallback(newItem);
        }
        close();
    });

    close();
}

function onCloseBtnClicked() {
    close();
}

function onAddArgBtnClicked() {
    args.value.push({value: ''});
}

function onRemoveArgBtnClicked(index: number) {
    args.value.splice(index, 1);
}

function onCommandVariableAdded(value: string) {
    item.value.command += value;
}

function onArgVariableAdded(index: number, value: string) {
    args.value[index].value += value;
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading">
            <v-card-title>Cron Job</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.name"
                            :rules="[
                                v => /^[a-z0-9-]{0,50}$/.test(v) || 'Invalid format'
                            ]"
                            :prefix="namePrefix"
                            label="Name"
                            density="compact"
                            persistent-hint
                            hint="[a-z0-9-]{1,50}"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.schedule"
                            :rules="[
                                v => /^(\*|([0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])|\*\/([0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])) (\*|([0-9]|1[0-9]|2[0-3])|\*\/([0-9]|1[0-9]|2[0-3])) (\*|([1-9]|1[0-9]|2[0-9]|3[0-1])|\*\/([1-9]|1[0-9]|2[0-9]|3[0-1])) (\*|([1-9]|1[0-2])|\*\/([1-9]|1[0-2])) (\*|([0-6])|\*\/([0-6]))$/.test(v) || 'Invalid format'
                            ]"
                            label="Schedule"
                            density="compact"
                            persistent-hint
                            hint="[minutes] [hours] [day-of-month] [month] [day-of-week]"
                        />
                    </v-col>

                    <v-col cols="12">
                        <div
                            class="d-flex"
                        >
                            <v-text-field
                                variant="outlined"
                                v-model="item.command"
                                label="Command"
                                spellcheck="false"
                                density="compact"
                                hide-details
                            />

                            <variable-btn
                                @add-variable="onCommandVariableAdded($event)"
                            />

                            <v-btn
                                icon
                                variant="plain"
                                color="primary"
                                size="small"
                                @click="onAddArgBtnClicked()"
                            >
                                <v-icon>fa fa-plus</v-icon>
                                <v-tooltip activator="parent" location="bottom">Add arg</v-tooltip>
                            </v-btn>
                        </div>
                    </v-col>

                    <v-col
                        v-for="(arg, i) in args" :key="i"
                        cols="12"
                    >
                        <div
                            class="d-flex"
                        >
                            <v-text-field
                                variant="outlined"
                                v-model="arg.value"
                                spellcheck="false"
                                density="compact"
                                label="Arg"
                                hide-details
                            />
                            <variable-btn
                                @add-variable="onArgVariableAdded(i, $event)"
                            />
                            <v-btn
                                icon
                                variant="plain"
                                color="warning"
                                size="small"
                                @click="onRemoveArgBtnClicked(i)"
                            >
                                <v-icon>fa fa-minus</v-icon>
                                <v-tooltip activator="parent" location="bottom">Remove arg</v-tooltip>
                            </v-btn>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <v-switch
                            v-model="item.include_deployment_environment_variables"
                            variant="outlined"
                            label="Include deployment environment variables"
                            density="compact"
                            color="secondary"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-card
                            class="px-4 py-2"
                        >
                            <h2>Container Image</h2>
                            <v-row dense
                                   class="mt-2"
                            >
                                <v-col cols="6">
                                    <v-select
                                        v-model="item.container_image_id"
                                        :loading="isLoadingContainerImageItems"
                                        :items="containerImageItems"
                                        item-title="name"
                                        item-value="id"
                                        variant="outlined"
                                        label="Container Image"
                                        density="compact"
                                        hide-details
                                    />
                                </v-col>

                                <v-col cols="6">
                                    <v-select
                                        v-model="item.container_image_pull_policy"
                                        :items="imagePullPolicies"
                                        item-title="name"
                                        item-value="identifier"
                                        variant="outlined"
                                        label="Image pull policy"
                                        density="compact"
                                        hide-details
                                    />
                                </v-col>

                                <v-col cols="6">
                                    <v-select
                                        v-model="item.container_image_tag_policy"
                                        :items="containerImageTagPolicies"
                                        item-title="name"
                                        item-value="identifier"
                                        variant="outlined"
                                        label="Tag policy"
                                        density="compact"
                                    />
                                </v-col>
                                <v-col cols="6"
                                       v-if="item.container_image_tag_policy == ContainerImageTagPolicies.Static"
                                >
                                    <v-text-field
                                        v-model="item.container_image_tag_value"
                                        variant="outlined"
                                        label="Tag"
                                        density="compact"
                                    />
                                </v-col>
                            </v-row>
                        </v-card>
                    </v-col>

                    <v-col cols="12">
                        <v-card
                            class="px-4 py-2"
                        >
                            <h2>Settings</h2>
                            <v-row dense
                                   class="mt-2"
                            >
                                <v-col cols="6">
                                    <v-select
                                        v-model="item.concurrency_policy"
                                        :items="concurrencyPolicies"
                                        item-title="name"
                                        item-value="identifier"
                                        variant="outlined"
                                        label="Concurrency policy"
                                        density="compact"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <v-select
                                        v-model="item.restart_policy"
                                        :items="restartPolicies"
                                        item-title="name"
                                        item-value="identifier"
                                        variant="outlined"
                                        label="Restart policy"
                                        density="compact"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        variant="outlined"
                                        v-model.number="item.successful_jobs_history_limit"
                                        type="number"
                                        label="Successful jobs history limit"
                                        density="compact"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        variant="outlined"
                                        v-model.number="item.failed_jobs_history_limit"
                                        type="number"
                                        label="Failed jobs history limit"
                                        density="compact"
                                    />
                                </v-col>
                            </v-row>
                        </v-card>
                    </v-col>

                    <v-col cols="12">
                        <v-card
                            class="px-4 py-2"
                        >
                            <h2>Resource Management</h2>
                            <v-row dense
                                   class="mt-2"
                            >
                                <v-col cols="6">
                                    <v-text-field
                                        v-model.number="item.cpu_request"
                                        type="number"
                                        variant="outlined"
                                        hint="100 = 0.1 CPU"
                                        persistent-hint
                                        label="CPU Request"/>
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model.number="item.cpu_limit"
                                        type="number"
                                        variant="outlined"
                                        hint="500 = 0.5 CPU"
                                        persistent-hint
                                        label="CPU Limit"/>
                                </v-col>

                                <v-col cols="6">
                                    <v-text-field
                                        v-model.number="item.memory_request"
                                        type="number"
                                        variant="outlined"
                                        hint="190.73 = 200 MB"
                                        persistent-hint
                                        label="Memory Request"/>
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model.number="item.memory_limit"
                                        type="number"
                                        variant="outlined"
                                        hint="953.67 = 1 GB"
                                        persistent-hint
                                        label="Memory Limit"/>
                                </v-col>
                            </v-row>
                        </v-card>
                    </v-col>

                </v-row>
            </v-card-text>
            <v-divider/>
            <v-card-actions>
                <v-spacer/>
                <v-btn
                    variant="tonal"
                    color="grey"
                    prepend-icon="fa fa-circle-xmark"
                    @click="onCloseBtnClicked">
                    Close
                </v-btn>

                <v-btn
                    flat
                    variant="tonal"
                    prepend-icon="fa fa-check"
                    color="green"
                    @click="onSaveBtnClicked">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
