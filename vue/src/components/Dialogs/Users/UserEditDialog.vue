<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {RbacRole, User} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import AuthService from "@/services/AuthService";

export interface UserEditDialog_Input {
    user: User;
}

const props = defineProps<{ input: UserEditDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);
const isLoading = ref(false);

const item = ref<User>(new User());
const isMe = ref<boolean>(false);
const password = ref('');
const passwordConfirm = ref('');

const roles = ref<RbacRole[]>([]);
const roleProps = ref<{ title: string, subtitle: string }[]>([]);
const isLoadingRoles = ref(false);
const selectedRoles = ref<number[]>([]);

const isMFAEnabled = ref<boolean>(false);
const isMFASetup = ref<boolean>(false);
const isMFASetupLoading = ref<boolean>(false);
const mfaQRImageDataUri = ref<string>();
const mfaSetupCode = ref<string>();
const mfaSetupVerificationCode = ref<string>();
const isMFASetupVerificationCodeBtnLoading = ref<boolean>(false);
watch(mfaSetupVerificationCode, newCode => {
    if (newCode !== undefined && newCode.length == 6) {
        onMFAVerificationCodeSaveBtnClicked();
    }
});

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
            roleProps.value = rbacRoles.map(role => {
                return {
                    title: role.name ?? '',
                    subtitle: role.description ?? '',
                }
            });
            isLoadingRoles.value = false;
        })

    if (props.input.user.exists()) {
        isLoading.value = true;
        showDialog.value = true;
        Api.users().getById(props.input.user.id!).find(items => {
            item.value = items[0];
            selectedRoles.value = item.value.rbac_roles?.map(role => role.id!) ?? [];
            isMFAEnabled.value = props.input.user.has_mfa_secret_hash;
            isMe.value = props.input.user.id == AuthService.currentAuthUser?.id;
            isLoading.value = false;
        });
    } else {
        item.value = props.input.user;
        isMFAEnabled.value = props.input.user.has_mfa_secret_hash;
        isMe.value = false;
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

function onEnableMFAToggleChanged() {
    if (isMFAEnabled.value) {
        isMFASetupLoading.value = true;
        const api = Api.users().mfaSetupPrepareGet();
        api.setWithCredentials(true);
        api.find(result => {
            mfaQRImageDataUri.value = result[0].qrCodeDataUri;
            mfaSetupCode.value = result[0].setupCode;
            isMFASetupLoading.value = false;
            isMFASetup.value = true;
        });
    } else {
        onMFARemoveBtnClicked();
    }
}

function onMFAVerificationCodeSaveBtnClicked() {
    isMFASetupVerificationCodeBtnLoading.value = true;
    const api = Api.users().mfaSetupVerifyPut().code(mfaSetupVerificationCode.value);
    api.setWithCredentials(true);
    api.save(null, result => {
        isMFASetupVerificationCodeBtnLoading.value = false;

        bus.emit('toast', {
            text: result.value ? 'Two-factor authenticated saved' : 'Failed to verify code, try again',
        });

        if (result.value) {
            isMFASetup.value = false;
            isMFAEnabled.value = true;
        }
    });
}

function onMFARemoveBtnClicked() {
    Api.users().mfaSetupRemovePut().save(null);
    isMFASetup.value = false;
    isMFAEnabled.value = false;
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
                            :multiple="true">
                            <template v-slot:item="{ props, item }">
                                <v-list-item
                                    v-bind="props"
                                    :subtitle="item.raw.description"
                                />
                            </template>
                        </v-select>
                    </v-col>

                    <v-col
                        cols="12 mb-6"
                    >
                        <v-card
                            class="px-4"
                            :loading="isMFASetupLoading"
                        >
                            <v-switch
                                v-model="isMFAEnabled"
                                density="compact"
                                label="Two-factor authentication"
                                color="primary"
                                @change="onEnableMFAToggleChanged"
                                :disabled="!isMe"
                            />
                            <v-row
                                v-if="isMe && isMFAEnabled && isMFASetup"
                            >
                                <v-col cols="12">
                                    <span class="d-block">Authenticator apps and browser extensions like <a
                                        href="https://support.1password.com/one-time-passwords/" target="_blank">1Password</a>, <a
                                        href="https://www.microsoft.com/en-us/security/mobile-authenticator-app"
                                        target="_blank">Microsoft Authenticator</a>, etc. generate one-time passwords that are used as a second factor to verify your identity when prompted during sign-in.</span>
                                    <span class="d-block mt-1 font-weight-bold">Scan the QR code</span>
                                    <span class="d-block">Use an authenticator app or browser extension to scan.</span>
                                </v-col>
                                <v-col cols="12">
                                    <v-img
                                        height="200px"
                                        weight="200px"
                                        :src="mfaQRImageDataUri"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <span class="d-block">You can also use the setup key to manually configure your authenticator app: <strong>{{
                                            mfaSetupCode
                                        }}</strong></span>
                                </v-col>
                                <v-col cols="12">
                                    <div class="d-flex">
                                        <v-text-field
                                            v-model="mfaSetupVerificationCode"
                                            density="compact" hide-details
                                            variant="outlined"
                                            label="Verify the code form the app"
                                            placeholder="XXXXXX"
                                            style="max-width: 300px;"
                                        />
                                        <v-btn
                                            variant="outlined"
                                            :loading="isMFASetupVerificationCodeBtnLoading"
                                            class="my-auto ml-auto"
                                            @click="onMFAVerificationCodeSaveBtnClicked"
                                        >
                                            Save
                                        </v-btn>
                                    </div>
                                </v-col>
                            </v-row>
                            <v-row
                                v-if="isMFAEnabled && !isMFASetupLoading && !isMFASetup"
                            >
                                <v-col cols="12">
                                    <span class="font-weight-bold">Two-factor is enabled</span>
                                </v-col>
                                <v-col
                                    v-if="isMe"
                                    cols="12">
                                    <v-btn
                                        @click="onMFARemoveBtnClicked"
                                        variant="outlined"
                                        color="warning"
                                    >
                                        Remove
                                    </v-btn>
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
