<script setup lang="ts">
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import DateView from "@/components/Modules/Common/DateView.vue";
import type {KubernetesPod} from "@/core/services/Deploy/Api";
import {Api} from "@/core/services/Deploy/Api";
import {VTextField} from "vuetify/components/VTextField";
import type {QuickCommand} from "@/components/Modules/PodTerminal/QuickCommand";

const props = defineProps<{
    pod: KubernetesPod;
    quickCommandItems: QuickCommand[];
}>();

defineExpose({
    reload
})

interface Row {
    date: Date,
    line: string;
}

const logs = ref<Row[]>([]);
const container = ref<HTMLPreElement>();
const commandInputView = ref<VTextField>();
const pod = ref<KubernetesPod>();
const commandInput = ref('');
const isRunningCommand = ref(false);
const commandHistory = ref<string[]>([]);
const currentCommandHistoryIndex = ref(0);
const showQuickCommandsMenu = ref(false);

// <editor-fold desc="Functions">

onMounted(() => {
    watch(() => props.pod, newValue => {
        reload();
    });
    reload();
});

onUnmounted(() => {

});

function reload() {
    pod.value = props.pod;
    logs.value = [];
    commandHistory.value = [];
    currentCommandHistoryIndex.value = 0;
}

// </editor-fold>

// <editor-fold desc="View Binding Functions">

function onCommandSubmitted() {
    if (commandInput.value!.length == 0) {
        return;
    }

    isRunningCommand.value = true;

    logs.value.push({
        date: new Date(),
        line: `> ${commandInput.value}`,
    });

    commandHistory.value.push(commandInput.value);
    currentCommandHistoryIndex.value = commandHistory.value.length;

    Api.kubernetes().execPutByNamespaceByNameByContainer(pod.value?.namespace!, pod.value?.pod!, pod.value?.container!)
        .command(commandInput.value)
        .save(null, response => {
            if (response) {
                const newLines = response.lines?.map(line => {
                    return {
                        date: new Date(),
                        line: line
                    }
                }) ?? [];

                logs.value.push(...newLines);

                clearCommandInput();
                focusCommandInput();
            }

            isRunningCommand.value = false;
        });
}

function onCodeBlockClicked() {
    focusCommandInput();
}

function onCommandInputUpKeyClicked() {
    currentCommandHistoryIndex.value--;
    insertFromHistory();
}

function onCommandInputDownKeyClicked() {
    currentCommandHistoryIndex.value++;
    insertFromHistory();
}

function onCommandInputEscKeyClicked() {
    clearCommandInput();
}

function insertFromHistory() {
    if (commandHistory.value.length > currentCommandHistoryIndex.value && currentCommandHistoryIndex.value >= 0) {
        commandInput.value = commandHistory.value[currentCommandHistoryIndex.value];
    }
}

function onQuickCommandClicked(item: QuickCommand) {
    commandInput.value = item.command;
    focusCommandInput();
}

// </editor-fold>

function focusCommandInput() {
    setTimeout(() => {
        (commandInputView.value as any)?.focus();
    });
}

function clearCommandInput() {
    currentCommandHistoryIndex.value = commandHistory.value.length;
    commandInput.value = '';
}

</script>

<template>

    <div class="d-flex flex-column w-100 h-100 tab-wrapper">
        <v-toolbar
            bg-color="secondary"
            density="compact"
            flat
            color="blue-grey lighten-5"
            class="d-flex w-100 flex-column"
        >
            <div class="d-flex flex-grow-1 px-5 ga-6">
                <div class="d-flex flex-column">
                    <span>Pod name</span>
                    <strong>{{ pod?.pod || 'Loading...' }}</strong>
                </div>
                <div class="d-flex flex-column">
                    <span>Container name</span>
                    <strong>{{ pod?.container }}</strong>
                </div>
                <div class="d-flex flex-column">
                    <span>Pod created</span>
                    <DateView :date-string="pod?.created"/>
                </div>
                <div class="d-flex flex-column">
                    <span>Pod status</span>
                    <strong>{{ pod?.status }}</strong>
                </div>
            </div>

        </v-toolbar>

        <v-progress-linear
            v-if="isRunningCommand"
            indeterminate/>

        <code
            ref="container"
            @click="onCodeBlockClicked"
        >
            <span
                class="d-flex flex-nowrap"
                v-for="(line, i) in logs" :key="i">
                <DateView
                    class="date-view"
                    text-format="DD/MM-YY HH:mm:ss"
                    :date="line.date"/>
                <span class="code-line">{{ line.line }}</span>
            </span>
        </code>

        <div
            class="flex-grow-0 d-flex terminal-input"
        >
            <v-menu
                v-model="showQuickCommandsMenu"
                left
                min-width="250"
                offset-y
            >
                <template v-slot:activator="{ props }">
                    <v-btn
                        v-bind="props"
                        x-small icon
                        variant="plain"
                    >
                        <v-icon>fa fa-cog</v-icon>
                        <v-tooltip activator="parent" location="bottom">Quick Commands</v-tooltip>
                    </v-btn>
                </template>
                <v-list
                    class="list-items">
                    <v-list-item
                        v-for="(item, i) in props.quickCommandItems" :key="i"
                        @click="onQuickCommandClicked(item)"
                    >
                        <v-list-item-title>
                            {{ item.name }}
                        </v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-menu>

            <v-text-field
                ref="commandInputView"
                v-model="commandInput"
                @keydown.enter.prevent="onCommandSubmitted"
                @keydown.up.prevent="onCommandInputUpKeyClicked"
                @keydown.down.prevent="onCommandInputDownKeyClicked"
                @keydown.esc.prevent.stop="onCommandInputEscKeyClicked"
                class="px-2 my-auto"
                variant="plain"
                density="compact"
                single-line
                hide-details
                clearable
                autofocus
                :disabled="isRunningCommand"
                spellcheck="false"
            />
        </div>
    </div>

</template>

<style scoped>

.date-view {
    width: 176px;
    overflow: hidden;
    color: #b0b2b2;
    flex-shrink: 0;
    background: rgb(225, 231, 233, .8);
    filter: dropShadow(0px 2px 8px rgba(0, 0, 0, 0.8));
    border-right: 1px solid rgba(0, 0, 0, 0.1);
    margin-right: .5rem;
    position: sticky;
    left: -16px;
    padding-left: 10px;
}

code {
    flex-grow: 1;
    overflow-x: auto;
}

.code-line {
    white-space: pre;
    display: block;

    /*overflow-x: auto;*/
}

.terminal-input {
    background-color: black;
    color: limegreen;
    font-family: Consolas, Monaco, Lucida Console, Liberation Mono, DejaVu Sans Mono, Bitstream Vera Sans Mono, Courier New;
}

.list-wrapper {
    min-width: 120px;
}

.v-list-item {
    min-height: unset;
}

.v-list-item-title {
    font-size: 11px !important;
}
</style>
