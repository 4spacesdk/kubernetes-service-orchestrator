<script setup lang="ts">
import {ref} from 'vue';
import moment from "moment";
import type {GeneratedSecret, GeneratedSecretVersion} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

/**
 * Generated secrets by name, each with the values it no longer has folded in under it - never a
 * value until somebody asks, and the asking is recorded by the server. Where they are shown from
 * and what may be done to them is the caller's: a live owner's can be rotated, set and restored,
 * a deleted one's only shown.
 */
const props = defineProps<{
    rows: GeneratedSecret[],
    /** A value: the current one, or the version given. */
    reveal: (name: string, version?: number) => Promise<string>,
    editable: boolean,
    noDataText: string,
}>();

const emit = defineEmits<{
    rotate: [row: GeneratedSecret],
    set: [row: GeneratedSecret],
    restore: [row: GeneratedSecret, version: GeneratedSecretVersion],
}>();

const expanded = ref<string[]>([]);
/** The values asked for, by `name#version`, until they are hidden again. */
const revealed = ref<Record<string, string>>({});
const headers = [
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Made as', key: 'recipe', sortable: false},
    {title: 'Version', key: 'version', sortable: false},
    {title: 'Made', key: 'made', sortable: false},
    {title: 'Rotated', key: 'rotated', sortable: false},
    {title: '', key: 'actions', sortable: false, align: 'end' as const},
];

function key(name: string, version?: number) {
    return `${name}#${version ?? 'current'}`;
}

function when(time?: string) {
    return time ? moment(time).format('D/M-YY HH:mm') : '';
}

function onRevealClicked(name: string, version?: number) {
    const at = key(name, version);
    if (revealed.value[at] !== undefined) {
        delete revealed.value[at];
        return;
    }
    props.reveal(name, version).then(value => revealed.value[at] = value);
}

function onCopyClicked(at: string) {
    navigator.clipboard.writeText(revealed.value[at] ?? '').then(() => bus.emit('toast', {text: 'Copied to clipboard'}));
}

/** What is shown is the state as it was: a new list hides every value again. */
function forget() {
    revealed.value = {};
}

defineExpose({forget});
</script>

<template>
    <v-data-table-server
        :headers="headers"
        :items-length="rows.length"
        :items="rows"
        :items-per-page="-1"
        item-value="name"
        v-model:expanded="expanded"
        show-expand
        class="table"
        density="compact">
        <template v-slot:item.name="{ item }">
            <div>{{ item.name }}</div>
            <div v-if="revealed[key(item.name!)] !== undefined" class="d-flex align-center ga-1">
                <code class="text-body-small value">{{ revealed[key(item.name!)] }}</code>
                <v-btn variant="plain" size="x-small" icon @click="onCopyClicked(key(item.name!))">
                    <v-icon>fa fa-copy</v-icon>
                    <v-tooltip activator="parent" location="bottom">Copy</v-tooltip>
                </v-btn>
            </div>
        </template>
        <template v-slot:item.recipe="{ item }">
            <span v-if="item.pending" class="text-medium-emphasis">made anew at the next deploy</span>
            <code v-else class="text-body-small">{{ item.recipe }}</code>
        </template>
        <template v-slot:item.version="{ item }">
            {{ item.version }}
            <span v-if="item.origin && item.origin !== 'made' && !item.pending" class="text-medium-emphasis text-body-small ml-1">{{ item.origin }}</span>
        </template>
        <template v-slot:item.made="{ item }">{{ when(item.made) }}</template>
        <template v-slot:item.rotated="{ item }">{{ when(item.rotated) }}</template>
        <template v-slot:item.actions="{ item }">
            <div class="d-flex justify-end ga-1">
                <v-btn variant="plain" color="primary" size="small" :disabled="item.pending" @click="onRevealClicked(item.name!)">
                    <v-icon>{{ revealed[key(item.name!)] !== undefined ? 'fa fa-eye-slash' : 'fa fa-eye' }}</v-icon>
                    <v-tooltip activator="parent" location="bottom">{{ revealed[key(item.name!)] !== undefined ? 'Hide' : 'Show - it is recorded' }}</v-tooltip>
                </v-btn>
                <template v-if="editable">
                    <v-btn variant="plain" color="primary" size="small" @click="emit('set', item)">
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Set a value by hand</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="warning" size="small" @click="emit('rotate', item)">
                        <v-icon>fa fa-rotate</v-icon>
                        <v-tooltip activator="parent" location="bottom">Rotate</v-tooltip>
                    </v-btn>
                </template>
            </div>
        </template>
        <template v-slot:item.data-table-expand="{ item, internalItem, isExpanded, toggleExpand }">
            <v-btn
                v-if="item.versions?.length"
                variant="plain"
                size="small"
                density="comfortable"
                icon
                @click="toggleExpand(internalItem)">
                <v-icon>{{ isExpanded(internalItem) ? 'fa fa-chevron-up' : 'fa fa-chevron-down' }}</v-icon>
                <v-tooltip activator="parent" location="bottom">{{ item.versions!.length }} earlier {{ item.versions!.length === 1 ? 'value' : 'values' }}</v-tooltip>
            </v-btn>
        </template>
        <template v-slot:expanded-row="{ columns, item }">
            <tr>
                <td :colspan="columns.length" class="py-2">
                    <v-table density="compact" class="versions">
                        <thead>
                        <tr>
                            <th>Version</th>
                            <th>Made as</th>
                            <th>Made</th>
                            <th>Until</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="version in item.versions" :key="version.version">
                            <td>
                                <div>
                                    {{ version.version }}
                                    <span v-if="version.origin !== 'made'" class="text-medium-emphasis text-body-small ml-1">{{ version.origin }}</span>
                                </div>
                                <div v-if="revealed[key(item.name!, version.version)] !== undefined" class="d-flex align-center ga-1">
                                    <code class="text-body-small value">{{ revealed[key(item.name!, version.version)] }}</code>
                                    <v-btn variant="plain" size="x-small" icon @click="onCopyClicked(key(item.name!, version.version))">
                                        <v-icon>fa fa-copy</v-icon>
                                        <v-tooltip activator="parent" location="bottom">Copy</v-tooltip>
                                    </v-btn>
                                </div>
                            </td>
                            <td><code class="text-body-small">{{ version.recipe }}</code></td>
                            <td>{{ when(version.made) }}</td>
                            <td>{{ when(version.retired) }}</td>
                            <td>
                                <div class="d-flex justify-end ga-1">
                                    <v-btn variant="plain" color="primary" size="small" @click="onRevealClicked(item.name!, version.version)">
                                        <v-icon>{{ revealed[key(item.name!, version.version)] !== undefined ? 'fa fa-eye-slash' : 'fa fa-eye' }}</v-icon>
                                        <v-tooltip activator="parent" location="bottom">{{ revealed[key(item.name!, version.version)] !== undefined ? 'Hide' : 'Show - it is recorded' }}</v-tooltip>
                                    </v-btn>
                                    <v-btn v-if="editable" variant="plain" color="warning" size="small" @click="emit('restore', item, version)">
                                        <v-icon>fa fa-clock-rotate-left</v-icon>
                                        <v-tooltip activator="parent" location="bottom">Make it the current value again</v-tooltip>
                                    </v-btn>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </v-table>
                </td>
            </tr>
        </template>
        <template v-slot:no-data>{{ noDataText }}</template>
    </v-data-table-server>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}

.versions {
    background: transparent;
}

/* A 64 character value would push the actions out of sight. */
.value {
    word-break: break-all;
}
</style>
