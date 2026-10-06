<script setup lang="ts">
import { useListState } from "@/composables/useListState";
import NameLink from "@/components/Modules/Common/NameLink.vue";
import {computed, defineComponent, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useRouter} from "vue-router";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {ContainerImage, ContainerImageScan, DeploymentSpecification} from "@/core/services/Deploy/models";
import ScanCounts from "@/components/Modules/Setup/ContainerImages/ScanCounts/ScanCounts.vue";
import debounce from "lodash.debounce";
import { VersionControlProviders, WorkloadTypes } from "@/constants";
import { CopyNameStrategy, duplicateEntity } from "@/helpers/DuplicateEntity";
import PushService from "@/services/Push/PushService";
import { Events } from "@/services/Push/Events";
import type { PushSubscription } from "@/services/Push/PushSubscription";

const router = useRouter();

const emit = defineEmits<{
    (e: 'onItemEditClicked', item: ContainerImage): void
}>();

const itemCount = ref(0);
const rows = ref<ContainerImage[]>([]);
const headers = ref([
    {title: 'Image', key: 'name', sortable: true},
    {title: 'Deployments', key: 'running_deployment_ids', sortable: false, align: 'center' as const},
    {title: 'Vulnerabilities', key: 'vulnerabilities', sortable: false},
    {title: '', key: 'actions', sortable: false, align: 'end' as const},
]);
const isLoading = ref(true);
const options = ref({});

/** The scans of the running tags of the images on the page, by image - counts only. */
const scansByImage = ref<Record<number, ContainerImageScan[]>>({});

/** One per image on the page, so a scan that moves on shows in its row at once. */
let scanSubscriptions: PushSubscription[] = [];

const {search: searchValue, page, itemsPerPage, sortBy, applyOrdering, applyPaging} = useListState({
    sortable: {"name": "name"},
    defaultSort: {key: "name", order: "asc"},
});

onMounted(() => {
    bus.on('containerImageSaved', onItemSaved);

    getItems(false, true);
});

onUnmounted(() => {
    bus.off('containerImageSaved', onItemSaved);
    unsubscribeScans();
});

watch(searchValue, debounce(() => {
    getItems(true, true);
}, 500));

function onItemSaved() {
    getItems(true, true);
}

function getItems(doItems = true, doCount = false) {

    // Mark as Loading
    isLoading.value = true;

    // Prepare API call
    const api = Api.containerImages().get();

    if (searchValue.value?.length) {
        api
            .search('name', searchValue.value)
            .search('url', searchValue.value);
    }

    if (doItems) {
        applyPaging(api);
        applyOrdering(api);
        api
            .find(items => {
                rows.value = items;
                isLoading.value = false;
                loadScans(items);
                subscribeScans(items);
            });
    }

    // Count total amount of items
    if (doCount) {
        api.count(count => {
            itemCount.value = count;
        });
    }
}

function loadScans(images: ContainerImage[]) {
    if (!images.length) {
        scansByImage.value = {};
        return;
    }
    Api.containerImageScans().get()
        .whereIn('container_image_id', images.map(image => image.id!))
        .find(scans => {
            const byImage: Record<number, ContainerImageScan[]> = {};
            scans.forEach(scan => (byImage[scan.container_image_id!] ??= []).push(scan));
            scansByImage.value = byImage;
        });
}

function subscribeScans(images: ContainerImage[]) {
    unsubscribeScans();
    scanSubscriptions = images.map(image => PushService.subscribe(
        Events.ContainerImage_Scans_Changed(image.id!),
        (data) => onScanChanged(new ContainerImageScan(data.next)),
    ));
}

function unsubscribeScans() {
    scanSubscriptions.forEach(subscription => subscription.unsubscribe());
    scanSubscriptions = [];
}

/**
 * The status and counts come with the event, so a scan the row already shows is swapped in
 * place. A new one - a tag scanned for the first time - is read again with the rest, as the
 * list decides which scans a row shows.
 */
function onScanChanged(changed: ContainerImageScan) {
    const scans = scansByImage.value[changed.container_image_id!] ?? [];
    const index = scans.findIndex(scan => scan.id === changed.id);
    if (index === -1) {
        reloadScans();
        return;
    }
    scans[index] = changed;
}

const reloadScans = debounce(() => loadScans(rows.value), 500);

// <editor-fold desc="View functions">

function onImportBtnClicked() {
    bus.emit('containerRegistryImport', {});
}

function createItem() {
    bus.emit('containerImageEdit', {
        containerImage: ContainerImage.Create(),
    });
}

/**
 * What could make the image run more securely - see `SecurityAdvice` - cut to what is about
 * running as non-root. The rest, seccomp among it, is on the image's Security tab: it would be on
 * every image made before kso read images, and a mark on every row is no mark.
 */
function adviceOf(item: ContainerImage): { key: string; level: string; text: string }[] {
    try {
        return (JSON.parse(item.security_advice ?? "[]") as { key: string; level: string; text: string }[])
            .filter(line => line.key == 'run_as_non_root');
    } catch {
        return [];
    }
}

function onEditItemBtnClicked(item: ContainerImage) {
    bus.emit('containerImageEdit', {
        containerImage: item,
    });
}

function onTagsItemBtnClicked(item: ContainerImage) {
    bus.emit('containerImageTags', {
        containerImage: item,
    });
}

function onCopyUrlClicked(item: ContainerImage) {
    navigator.clipboard.writeText(item.url ?? '').then(() => {
        bus.emit('toast', {
            text: 'Url copied to clipboard',
        });
    });
}

function onScansItemBtnClicked(item: ContainerImage) {
    bus.emit('containerImageScans', {
        containerImage: item,
    });
}

interface SpecificationUse {
    id: number;
    name: string;
    roles: string[];
}

/** The specifications that use the image - as their workload, migration job, init container, sidecar or cron job. */
function specificationsOf(item: ContainerImage): SpecificationUse[] {
    try {
        return JSON.parse(item.specification_uses ?? '[]');
    } catch {
        return [];
    }
}

function onSpecificationClicked(use: SpecificationUse) {
    router.push(`/setup/deployment-specifications/${use.id}`);
}

/**
 * A specification for an image no specification uses yet: a Deployment of it, named after it,
 * opened once it is made.
 */
function onCreateSpecificationClicked(item: ContainerImage) {
    const specification = DeploymentSpecification.Create(WorkloadTypes.Deployment);
    specification.container_image_id = item.id;
    specification.name = (item.url ?? item.name ?? '').split('/').pop()?.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
    bus.emit('deploymentSpecificationCreate', {
        deploymentSpecification: specification,
        onCreated: (created: DeploymentSpecification) => router.push(`/setup/deployment-specifications/${created.id}`),
    });
}

function onRunningDeploymentsClicked(item: ContainerImage) {
    bus.emit('containerImageDeployments', {
        containerImage: item,
    });
}

function onDuplicateItemBtnClicked(item: ContainerImage) {
    // Read the row again rather than copying what the table holds, so fields the list
    // does not ask for still make it into the copy.
    Api.containerImages().getById(item.id!).find(items => {
        bus.emit('containerImageEdit', {
            containerImage: duplicateEntity(items[0], ContainerImage, CopyNameStrategy.Label),
        });
    });
}

function deleteItem(item: ContainerImage) {
    bus.emit('confirm', {
        body: `Do you want to delete "${item.name}"?`,
        confirmIcon: 'fa fa-trash',
        confirmColor: 'error',

        responseCallback: (confirmed: boolean) => {
            if (confirmed) {
                Api.containerImages().deleteById(item.id!).delete(() => bus.emit('containerImageSaved'));
            }
        }
    });
}

// </editor-fold>

</script>

<template>
    <div class="h-100 content-wrapper">

        <v-toolbar
            density="compact"
            flat
            color="toolbar"
            dark
        >
            <v-toolbar-title>Container Images</v-toolbar-title>

            <v-text-field data-shortcut="search"
                v-model="searchValue"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Search"
                clearable
            />

            <v-spacer></v-spacer>
            <v-btn small class="" @click="onImportBtnClicked()"
                   prepend-icon="fa fa-download"
            >
                Import
            </v-btn>
            <v-btn data-shortcut="create" small class="" @click="createItem()"
                   prepend-icon="fa fa-plus"
            >
                Create
            </v-btn>
        </v-toolbar>

        <v-data-table-server
            :headers="headers"
            :items-length="itemCount"
            :items="rows"
            :loading="isLoading"
            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            v-model:sort-by="sortBy"
            class="table"
            density="comfortable"
            @update:options="options = $event; getItems()">
            <template v-slot:item.name="{ item }">
                <div class="py-2">
                    <div class="d-flex align-center ga-2">
                        <name-link @click="onEditItemBtnClicked(item)">{{ item.name }}</name-link>
                        <span v-if="item.pull_secret" class="d-inline-flex">
                            <v-icon size="x-small" color="blue-grey" icon="fa fa-key" />
                            <v-tooltip activator="parent" location="bottom">Pull secret: {{ item.pull_secret }}</v-tooltip>
                        </span>
                        <span v-if="adviceOf(item).length" class="d-inline-flex cursor-pointer" @click="onEditItemBtnClicked(item)">
                            <v-icon
                                size="x-small"
                                :color="adviceOf(item).some(line => line.level == 'warning') ? 'warning' : 'blue-grey'"
                                icon="fa fa-shield-halved" />
                            <v-tooltip activator="parent" location="bottom">
                                <div v-for="(line, index) in adviceOf(item)" :key="index">{{ line.text }}</div>
                            </v-tooltip>
                        </span>
                        <span v-if="item.version_control_enabled" class="d-inline-flex">
                            <v-icon size="x-small" color="blue-grey"
                                    :icon="item.version_control_provider === VersionControlProviders.GitHub ? 'fa-brands fa-github' : 'fa fa-code-branch'" />
                            <v-tooltip activator="parent" location="bottom">
                                {{ item.version_control_provider }}<template v-if="item.version_control_repository_name">: {{ item.version_control_repository_name }}</template>
                            </v-tooltip>
                        </span>
                    </div>
                    <div class="text-medium-emphasis image-url" @click="onCopyUrlClicked(item)">
                        <template v-for="(part, index) in (item.url ?? '').split('/')" :key="index"><template v-if="index">/<wbr></template>{{ part }}</template>
                        <v-tooltip activator="parent" location="bottom">Copy to clipboard</v-tooltip>
                    </div>
                </div>
            </template>
            <template v-slot:item.running_deployment_ids="{ item }">
                <span v-if="!item.running_deployment_ids?.length" class="text-disabled">0</span>
                <name-link v-else @click="onRunningDeploymentsClicked(item)">
                    {{ item.running_deployment_ids.length }}
                    <v-tooltip activator="parent" location="bottom">Show running deployments</v-tooltip>
                </name-link>
            </template>
            <template v-slot:item.vulnerabilities="{ item }">
                <div class="d-flex flex-column ga-1 py-2 cursor-pointer" @click="onScansItemBtnClicked(item)">
                    <div v-for="scan in scansByImage[item.id!] ?? []" :key="scan.id" class="d-flex align-center ga-2 text-no-wrap">
                        <span class="text-body-small text-medium-emphasis">{{ scan.tag }}</span>
                        <ScanCounts :scan="scan" />
                    </div>
                </div>
            </template>
            <template v-slot:item.actions="{ item }">
                <div class="d-flex justify-end align-center ga-1 text-no-wrap">
                    <v-btn
                        variant="plain" color="primary"
                        @click="onEditItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-pen</v-icon>
                        <v-tooltip activator="parent" location="bottom">Edit</v-tooltip>
                    </v-btn>

                    <!-- Its specification: made from it when there is none, opened when there is one, picked when there are more. -->
                    <v-btn
                        v-if="!specificationsOf(item).length"
                        variant="plain" color="primary"
                        @click="onCreateSpecificationClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-file-circle-plus</v-icon>
                        <v-tooltip activator="parent" location="bottom">Create a specification from it</v-tooltip>
                    </v-btn>
                    <v-btn
                        v-else-if="specificationsOf(item).length === 1"
                        variant="plain" color="primary"
                        @click="onSpecificationClicked(specificationsOf(item)[0])"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-file-lines</v-icon>
                        <v-tooltip activator="parent" location="bottom">
                            {{ specificationsOf(item)[0].name }} - its {{ specificationsOf(item)[0].roles.join(', ') }}
                        </v-tooltip>
                    </v-btn>
                    <v-menu v-else location="bottom end">
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" variant="plain" color="primary" size="small" density="comfortable" icon>
                                <v-badge :content="specificationsOf(item).length" color="secondary" floating>
                                    <v-icon>fa fa-file-lines</v-icon>
                                </v-badge>
                                <v-tooltip activator="parent" location="bottom">The specifications that use it</v-tooltip>
                            </v-btn>
                        </template>
                        <v-list density="compact">
                            <v-list-item
                                v-for="use in specificationsOf(item)"
                                :key="use.id"
                                prepend-icon="fa fa-file-lines"
                                :title="use.name"
                                :subtitle="use.roles.join(', ')"
                                @click="onSpecificationClicked(use)"/>
                        </v-list>
                    </v-menu>

                    <v-btn
                        variant="plain" color="primary"
                        @click="onScansItemBtnClicked(item)"
                        size="small"
                        density="comfortable"
                        icon
                    >
                        <v-icon>fa fa-shield-halved</v-icon>
                        <v-tooltip activator="parent" location="bottom">Vulnerabilities</v-tooltip>
                    </v-btn>

                    <!-- The rarer ones, and Delete out of reach of a slip from Edit. -->
                    <v-menu location="bottom end">
                        <template v-slot:activator="{ props }">
                            <v-btn v-bind="props" variant="plain" color="primary" aria-label="More" size="small" density="comfortable" icon>
                                <v-icon>fa fa-ellipsis-vertical</v-icon>
                            </v-btn>
                        </template>
                        <v-list density="compact">
                            <v-list-item v-if="item.container_registry_id" prepend-icon="fa fa-tags" title="List tags" @click="onTagsItemBtnClicked(item)" />
                            <v-list-item prepend-icon="fa fa-clone" title="Duplicate" @click="onDuplicateItemBtnClicked(item)" />
                            <v-list-item prepend-icon="fa fa-trash" title="Delete" base-color="error" @click="deleteItem(item)" />
                        </v-list>
                    </v-menu>
                </div>
            </template>
        </v-data-table-server>
    </div>
</template>

<style scoped>
.table > *,
.table {
    background: transparent;
}

/* Long registry paths break after a slash, and only mid-word when a part will not fit at all. */
.image-url {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.6875rem;
    line-height: 1.4;
    overflow-wrap: anywhere;
    max-width: 640px;
    cursor: copy;
}

.image-url:hover {
    text-decoration: underline dotted;
}
</style>
