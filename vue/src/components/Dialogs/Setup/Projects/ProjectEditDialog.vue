<script setup lang="ts">
import { useDialogSave } from "@/composables/useDialogSave";
import {onMounted, onUnmounted, ref} from 'vue'
import {Project, User} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface ProjectEditDialog_Input {
    project: Project;
}

const props = defineProps<{input: ProjectEditDialog_Input, events: DialogEventsInterface}>();

const { form, isSaving, save } = useDialogSave();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<Project>(new Project());

const users = ref<User[]>([]);
const isLoadingUsers = ref(false);
const selectedUsers = ref<number[]>([]);

const isFormValid = ref(false);
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
    isLoadingUsers.value = true;
    Api.users().get()
        .orderAsc('first_name')
        .find(items => {
            users.value = items;
            isLoadingUsers.value = false;
        });

    if (props.input.project.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.projects().getById(props.input.project.id!)
            .include('user')
            .find(items => {
                item.value = items[0];
                selectedUsers.value = item.value.users?.map(user => user.id!) ?? [];
                isLoading.value = false;
            });
    } else {
        item.value = props.input.project;
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('projectEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    const api = item.value!.exists() ? Api.projects().patchById(item.value!.id!) : Api.projects().post();

    // The members are set on their own below; sent along, the patch would write them as well.
    const data = {
        name: item.value!.name,
        description: item.value!.description ?? '',
    };

    save(api, data, newItem => {
        // Should the members fail, a second Save patches the project rather than creating another.
        item.value!.id = newItem.id;
        save(Api.projects().updateUsersPutById(newItem.id!), {values: selectedUsers.value}, savedItem => {
            bus.emit('projectSaved', savedItem);
            close();
        });
    });
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
            ref="form"
            v-model="isFormValid"
            @submit.prevent
        >
            <v-card
                class="w-100 h-100"
                :loading="isLoading"
                :disabled="isLoading">
                <v-card-title>Project</v-card-title>
                <v-divider/>
                <v-card-text>
                    <v-row density="compact">
                        <v-col cols="12">
                            <v-text-field
                                variant="outlined"
                                v-model="item.name"
                                label="Name"
                                :rules="rules.required"/>
                        </v-col>
                        <v-col cols="12">
                            <v-textarea
                                variant="outlined"
                                v-model="item.description"
                                rows="3"
                                auto-grow
                                label="Description"/>
                        </v-col>
                        <v-col cols="12">
                            <v-autocomplete
                                v-model="selectedUsers"
                                :items="users"
                                :loading="isLoadingUsers"
                                item-title="name"
                                item-value="id"
                                variant="outlined"
                                label="Members"
                                hint="The users the project is for - it decides what they see first, not what they can open"
                                persistent-hint
                                multiple
                                chips
                                closable-chips/>
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
                        :disabled="!isFormValid"
                        flat
                        variant="tonal"
                        prepend-icon="fa fa-check"
                        color="success"
                        :loading="isSaving"
                        @click="onSaveBtnClicked">
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-form>
    </v-dialog>
</template>

<style scoped>

</style>
