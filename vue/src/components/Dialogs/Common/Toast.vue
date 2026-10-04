<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import type {DialogEventsInterface} from "@/components/Dialogs/DialogEventsInterface";

export interface ToastDialog_Input {
    text: string;
    icon?: string;
    color?: string;
}

const props = defineProps<{input: ToastDialog_Input, events: DialogEventsInterface}>();

const used = ref(false);
const showDialog = ref(false);

/**
 * The text with a capital first letter - when it starts with one. A message that starts with a
 * quoted name, `'spant-pilot' already has its disk`, keeps the name as it is written.
 */
const text = computed(() => {
    const value = props.input.text ?? '';
    return /^[a-z]/.test(value) ? value.charAt(0).toUpperCase() + value.slice(1) : value;
});

// <editor-fold desc="Functions">

onMounted(() => {
    if (used.value) {
        return;
    }
    used.value = true;
    render();
});

function render() {
    showDialog.value = true;
}

function close() {
    showDialog.value = false;
    props.events.onClose();
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCloseBtnClicked() {
    close();
}

// </editor-fold>

</script>

<template>
    <v-snackbar
        v-model="showDialog"
        @update:model-value="$event ? '' : close()">

        <!-- Not a chip: a chip never wraps, and a long message ran off the edge. -->
        <div class="d-flex align-start ga-2 toast-text" :class="props.input.color ? `text-${props.input.color}` : ''">
            <v-icon v-if="props.input.icon" size="small" class="mt-1">{{ props.input.icon }}</v-icon>
            <span>{{ text }}</span>
        </div>

        <template v-slot:actions>
            <v-btn
                color="secondary"
                variant="text"
                @click="onCloseBtnClicked"
            >
                Close
            </v-btn>
        </template>
    </v-snackbar>
</template>

<style scoped>
.toast-text {
    white-space: normal;
    overflow-wrap: anywhere;
}
</style>
