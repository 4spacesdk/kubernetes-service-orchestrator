<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {RbacRole, User} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface UserEditDialog_Input {
    user: User;
}

const props = defineProps<{input: UserEditDialog_Input, events: DialogEventsInterface}>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<User>(new User());
const password = ref('');
const passwordConfirm = ref('');

const roles = ref<RbacRole[]>([]);
const isLoadingRoles = ref(false);
const selectedRoles = ref<number[]>([]);

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
    isLoadingRoles.value = true;
    Api.rbacRoles().get()
        .find(rbacRoles => {
            roles.value = rbacRoles;
            isLoadingRoles.value = false;
        })

    if (props.input.user.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.users().getById(props.input.user.id!).find(items => {
            item.value = items[0];
            selectedRoles.value = item.value.rbac_roles?.map(role => role.id!) ?? [];
            isLoading.value = false;
        });
    } else {
        item.value = props.input.user;
        showDialog.value = true;
    }
}

function close() {
    showDialog.value = false;
    bus.emit('userEditDialog_closed', item.value);
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    item.value.rbac_roles = roles.value.filter(role => selectedRoles.value.includes(role.id!));

    const api = item.value!.exists() ? Api.users().patchById(item.value!.id!) : Api.users().post();

    if (password.value?.length >= 6 && password.value.localeCompare(passwordConfirm.value) === 0) {
        item.value!.password = password.value;
    }

    api.save(item.value!, newItem => {
        bus.emit('userSaved', newItem);
        close();
    });

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
        height="60vh"
        width="60vw"
        v-model="showDialog">
        <v-card
            class="w-100 h-100"
            :loading="isLoading"
            :disabled="isLoading">
            <v-card-title>User</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row
                    dense>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="item.first_name"
                            label="First name"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="item.last_name"
                            label="Last name"/>
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            variant="outlined"
                            v-model="item.username"
                            label="E-mail"/>
                    </v-col>


                    <v-col cols="6">
                        <v-text-field
                            persistent-hint
                            hint="Enter your password to access this website"
                            variant="outlined"
                            v-model="password"
                            type="password"
                            label="Password"/>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            variant="outlined"
                            v-model="passwordConfirm"
                            type="password"
                            label="Confirm password"/>
                    </v-col>

                    <v-col cols="12">
                        <v-checkbox
                            v-model="item.renew_password"
                            color="accent"
                            density="compact"
                            label="Require password renewal on next login"
                        />
                    </v-col>


                    <v-col cols="12">
                        <v-select
                            v-model="selectedRoles"
                            label="Roles"
                            :items="roles"
                            :loading="isLoadingRoles"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            :multiple="true"
                        >
                            <template v-slot:item="{ props, item }">
                                <v-list-item
                                    v-bind="props"
                                    :title="item.raw.name"
                                    :subtitle="item.raw.description"
                                />
                            </template>
                        </v-select>
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
