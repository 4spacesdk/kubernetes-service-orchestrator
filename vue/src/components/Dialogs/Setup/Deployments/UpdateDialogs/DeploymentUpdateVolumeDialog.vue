<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {DeploymentVolume} from "@/core/services/Deploy/models";
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";
import VariableBtn from "@/components/Modules/Common/VariableBtn.vue";

export interface DeploymentUpdateVolumeDialog_Input {
    volume: DeploymentVolume;

    onSaveCallback: () => void;
}

const props = defineProps<{ input: DeploymentUpdateVolumeDialog_Input, events: DialogEventsInterface }>();

const used = ref(false);
const showDialog = ref(false);

const type = ref('');
const typeItems = ref([
    'nfs', 'csi',
]);
const mountPath = ref('');
const subPath = ref('');
const capacity = ref(0);
const volumeMode = ref('');
const volumeModeItems = ref([
    'Filesystem', 'Block',
]);
const reclaimPolicy = ref('');
const reclaimPolicyItems = ref([
    'Retain', 'Recycle', 'Delete',
]);
const nfsServer = ref('');
const nfsPath = ref('');
const storageClass = ref('');
const csiDriver = ref('');
const csiVolumeHandle = ref('');

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
    type.value = props.input.volume.type ?? '';
    mountPath.value = props.input.volume.mount_path ?? '';
    subPath.value = props.input.volume.sub_path ?? '';
    capacity.value = props.input.volume.capacity ?? 0;
    volumeMode.value = props.input.volume.volume_mode ?? volumeModeItems.value[0];
    reclaimPolicy.value = props.input.volume.reclaim_policy ?? reclaimPolicyItems.value[0];
    nfsServer.value = props.input.volume.nfs_server ?? '';
    nfsPath.value = props.input.volume.nfs_path ?? '';
    storageClass.value = props.input.volume.storage_class ?? '';
    csiDriver.value = props.input.volume.csi_driver ?? '';
    csiVolumeHandle.value = props.input.volume.csi_volume_handle ?? '';
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onSaveBtnClicked() {
    props.input.volume.type = type.value;
    props.input.volume.mount_path = mountPath.value;
    props.input.volume.sub_path = subPath.value;
    props.input.volume.capacity = capacity.value;
    props.input.volume.volume_mode = volumeMode.value;
    props.input.volume.reclaim_policy = reclaimPolicy.value;
    props.input.volume.nfs_server = nfsServer.value;
    props.input.volume.nfs_path = nfsPath.value;
    props.input.volume.storage_class = storageClass.value;
    props.input.volume.csi_driver = csiDriver.value;
    props.input.volume.csi_volume_handle = csiVolumeHandle.value;
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
        <v-card
            class="w-100 h-100">
            <v-card-title>Volumes</v-card-title>
            <v-divider/>
            <v-card-text>
                <v-row>
                    <v-col cols="6">
                        <v-select
                            v-model="type"
                            variant="outlined"
                            label="Type"
                            :items="typeItems"
                        />
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model="mountPath"
                            variant="outlined"
                            label="Mount Path"
                            hint="Path on Pod"
                            persistent-hint
                        />
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model="subPath"
                            variant="outlined"
                            label="Sub Path"
                            hint="Path on Volume"
                            persistent-hint
                        />
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model.number="capacity"
                            variant="outlined"
                            label="Capacity"
                            suffix="GB"
                            type="number"
                            persistent-hint
                            density="compact"
                        />
                    </v-col>
                    <v-col cols="6">
                        <v-select
                            v-model="volumeMode"
                            variant="outlined"
                            label="Volume Mode"
                            :items="volumeModeItems"
                        />
                    </v-col>
                    <v-col cols="6">
                        <v-select
                            v-model="reclaimPolicy"
                            variant="outlined"
                            label="Reclaim Policy"
                            :items="reclaimPolicyItems"
                        />
                    </v-col>
                    <v-col
                        v-if="type == 'nfs'"
                        cols="6">
                        <v-text-field
                            v-model="nfsServer"
                            variant="outlined"
                            label="NFS Server"
                        />
                    </v-col>
                    <v-col
                        v-if="type == 'nfs'"
                        cols="6">
                        <v-text-field
                            v-model="nfsPath"
                            variant="outlined"
                            label="NFS Path"
                        />
                    </v-col>
                    <v-col cols="6">
                        <v-text-field
                            v-model="storageClass"
                            variant="outlined"
                            label="Storage Class"
                        />
                    </v-col>
                    <v-col
                        v-if="type == 'csi'"
                        cols="6">
                        <v-text-field
                            v-model="csiDriver"
                            variant="outlined"
                            label="CSI Driver"
                            hint="nfs.csi.k8s.io"
                            persistent-hint
                        />
                    </v-col>
                    <v-col
                        v-if="type == 'csi'"
                        cols="6">
                        <div
                            class="d-flex"
                        >
                            <v-text-field
                                v-model="csiVolumeHandle"
                                variant="outlined"
                                label="CSI Volume Handle"
                            />
                            <variable-btn
                                @add-variable="item => csiVolumeHandle += item"
                            />
                        </div>
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
                    Done
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
