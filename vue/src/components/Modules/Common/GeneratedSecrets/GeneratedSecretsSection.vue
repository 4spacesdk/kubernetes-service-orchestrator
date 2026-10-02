<script setup lang="ts">
import {onMounted, ref} from 'vue';
import moment from "moment";
import {Api, type GeneratedSecret} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/**
 * The secrets kso made for a deployment or a workspace - `${secret.<name>}` and
 * `${workspace.secret.<name>}` in an environment variable. Made the first time a deploy asks,
 * and listed by name: the value is shown only when somebody asks, and that is recorded.
 */
const props = defineProps<{
    owner: 'deployment' | 'workspace',
    ownerId: number,
}>();

const isLoading = ref(false);
const rows = ref<GeneratedSecret[]>([]);
/** The values asked for, by name, until they are hidden again. */
const revealed = ref<Record<string, string>>({});
const headers = [
    {title: 'Name', key: 'name', sortable: false},
    {title: 'Made as', key: 'recipe', sortable: false},
    {title: 'Made', key: 'created', sortable: false},
    {title: 'Rotated', key: 'rotated', sortable: false},
    {title: '', key: 'actions', sortable: false, align: 'end' as const},
];

const api = () => props.owner === 'deployment' ? Api.deployments() : Api.workspaces();

onMounted(load);

function load() {
    isLoading.value = true;
    api().getSecretsGetById(props.ownerId).find(responses => {
        rows.value = responses[0]?.secrets ?? [];
        isLoading.value = false;
    });
}

function when(time?: string) {
    return time ? moment(time).format('D/M-YY HH:mm') : '';
}

function onRevealClicked(row: GeneratedSecret) {
    if (revealed.value[row.name!] !== undefined) {
        delete revealed.value[row.name!];
        return;
    }
    api().revealSecretPutById(props.ownerId).name(row.name!).save(null, response => {
        revealed.value[row.name!] = response.value ?? '';
    });
}

function onCopyClicked(name: string) {
    navigator.clipboard.writeText(revealed.value[name] ?? '').then(() => bus.emit('toast', {text: 'Copied to clipboard'}));
}

function onRotateClicked(row: GeneratedSecret) {
    bus.emit('confirm', {
        body: `Rotate ${row.name}? The next deploy makes a new value, as it is written then - a changed recipe included - and what was signed or opened with the old value stops working: signed links and open realtime connections, for instance.`,
        confirmIcon: 'fa fa-rotate',
        confirmColor: 'warning',
        responseCallback: (confirmed: boolean) => {
            if (!confirmed) {
                return;
            }
            api().rotateSecretPutById(props.ownerId).name(row.name!).save(null, response => {
                delete revealed.value[row.name!];
                rows.value = response.secrets ?? rows.value;
                bus.emit('toast', {text: `${row.name} rotated - deploy to put it to use`});
            });
        },
    });
}
</script>

<template>
    <page-section
        title="Secrets"
        flush
        hide-save
        :is-loading="isLoading">
        <p class="text-medium-emphasis pa-4 mb-0">
            Set an environment variable's value to <strong>Generated secret</strong>, give it a name and say what it is made of,
            and kso makes a random value the first time it deploys - the same in every container that uses the name. Rotating one
            makes it anew at the next deploy.
        </p>

        <v-data-table-server
            :headers="headers"
            :items-length="rows.length"
            :items="rows"
            :items-per-page="-1"
            class="table"
            density="compact">
            <template v-slot:item.name="{ item }">
                <div>{{ item.name }}</div>
                <div v-if="revealed[item.name!] !== undefined" class="d-flex align-center ga-1">
                    <code class="text-body-small">{{ revealed[item.name!] }}</code>
                    <v-btn variant="plain" size="x-small" icon @click="onCopyClicked(item.name!)">
                        <v-icon>fa fa-copy</v-icon>
                        <v-tooltip activator="parent" location="bottom">Copy</v-tooltip>
                    </v-btn>
                </div>
            </template>
            <template v-slot:item.recipe="{ item }">
                <span v-if="item.pending" class="text-medium-emphasis">made anew at the next deploy</span>
                <code v-else class="text-body-small">{{ item.recipe }}</code>
            </template>
            <template v-slot:item.created="{ item }">{{ when(item.created) }}</template>
            <template v-slot:item.rotated="{ item }">{{ when(item.rotated) }}</template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                    <v-btn variant="plain" color="primary" size="small" :disabled="item.pending" @click="onRevealClicked(item)">
                        <v-icon>{{ revealed[item.name!] !== undefined ? 'fa fa-eye-slash' : 'fa fa-eye' }}</v-icon>
                        <v-tooltip activator="parent" location="bottom">{{ revealed[item.name!] !== undefined ? 'Hide' : 'Show - it is recorded' }}</v-tooltip>
                    </v-btn>
                    <v-btn variant="plain" color="warning" size="small" @click="onRotateClicked(item)">
                        <v-icon>fa fa-rotate</v-icon>
                        <v-tooltip activator="parent" location="bottom">Rotate</v-tooltip>
                    </v-btn>
                </div>
            </template>
            <template v-slot:no-data>
                None yet - they are made when a deploy first asks for one.
            </template>
        </v-data-table-server>
    </page-section>
</template>

<style scoped>
:deep(.v-data-table-footer) {
    display: none;
}
</style>
