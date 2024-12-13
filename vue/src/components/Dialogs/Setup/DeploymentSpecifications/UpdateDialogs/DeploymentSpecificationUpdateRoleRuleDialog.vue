<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import {Api} from "@/core/services/Deploy/Api";
import type {KubernetesRbacRule} from "@/core/services/Deploy/Api";

export interface DeploymentSpecificationUpdateRoleRuleDialog_Input {
    roleRule: {
        apiGroup: string;
        resource: string;
        verbs: string;
    };

    onSaveCallback: () => void;
}

const props = defineProps<{
    input: DeploymentSpecificationUpdateRoleRuleDialog_Input,
    events: DialogEventsInterface
}>();

const used = ref(false);
const isLoading = ref(false);
const showDialog = ref(false);
const formIsValid = ref(false);

const apiGroupItems = ref<string[]>([]);
const resourceItems = ref<string[]>([]);
const verbItems = ref<string[]>([]);

const apiGroup = ref('');
const resource = ref('');
const verbs = ref<string[]>([]);

const rules = {
    required: [
        (value: any) => {
            if (value) {
                return true;
            }
            return 'Field is required';
        }
    ]
};

const availableRules = ref<KubernetesRbacRule[]>([]);

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;

    isLoading.value = true;
    Api.kubernetes().rbacShowGet()
        .find(response => {
            availableRules.value = response[0].rules ?? [];
            apiGroupItems.value = [...new Set(response[0].rules
                ?.map(rule => rule.apiGroup!)
                ?? [])];
            if (!apiGroup.value) {
                apiGroup.value = apiGroupItems.value.length ? apiGroupItems.value[0] : '';
            }
            onApiGroupChanged();
            isLoading.value = false;
        });

    render();
});

onUnmounted(() => {
});

function render() {
    apiGroup.value = props.input.roleRule.apiGroup ?? '';
    resource.value = props.input.roleRule.resource ?? '';
    verbs.value = props.input.roleRule.verbs.split(',');
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onApiGroupChanged() {
    resourceItems.value = availableRules.value
        .filter(rule => rule.apiGroup == apiGroup.value)
        .map(rule => rule.resource ?? '');

    // Reset resource and verbs
    if (!resource.value) {
        resource.value = resourceItems.value.length ? resourceItems.value[0] : '';
        verbs.value = [];
        verbItems.value = [];
    }
    onResourceChanged();
}

function onResourceChanged() {
    verbItems.value = availableRules.value
        .find(rule => rule.apiGroup == apiGroup.value && rule.resource == resource.value)
        ?.verbs ?? [];
}

function onSaveBtnClicked() {
    props.input.roleRule.apiGroup = apiGroup.value;
    props.input.roleRule.resource = resource.value;
    props.input.roleRule.verbs = verbs.value.join(',');
    props.input.onSaveCallback();
    close();
}

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-dialog
        persistent
        width="60vw"
        v-model="showDialog">
        <v-form
            v-model="formIsValid"
        >
            <v-card
                class="w-100 h-100"
                :loading="isLoading"
                :disabled="isLoading"
            >
                <v-card-title>Role Rule</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row>
                        <v-col cols="6">
                            <v-select
                                v-model="apiGroup"
                                variant="outlined"
                                label="Api Group"
                                :items="apiGroupItems"
                                @update:model-value="onApiGroupChanged"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-select
                                v-model="resource"
                                variant="outlined"
                                label="Resource"
                                :rules="rules.required"
                                :items="resourceItems"
                                @update:model-value="onResourceChanged"
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-select
                                v-model="verbs"
                                variant="outlined"
                                label="Verbs"
                                :rules="rules.required"
                                :items="verbItems"
                                multiple
                            />
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
                        :disabled="!formIsValid"
                        flat
                        variant="tonal"
                        prepend-icon="fa fa-check"
                        color="green"
                        @click="onSaveBtnClicked">
                        Done
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-form>
    </v-dialog>
</template>

<style scoped>

</style>
