<script setup lang="ts">
import {computed, onMounted, onUnmounted, ref} from 'vue';
import {ContainerImage, DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";

/**
 * The images the custom resource names - `image:` anywhere in it - and which of them is the
 * specification's container image. Tracked, it is listed under Container images, scanned and
 * auto updated like any other workload: its tag in the manifest becomes `${deployment.version}`,
 * and each deployment gets the tag as its version. See `CustomResourceImages`.
 */
const props = defineProps<{
    item: DeploymentSpecification
}>();

const VersionPlaceholder = '${deployment.version}';

interface Found {
    reference: string;
    repository: string;
    tag: string;
}

const images = ref<ContainerImage[]>([]);
const linking = ref('');

/** As the server reads them: line by line, as the manifest holds placeholders. */
const found = computed<Found[]>(() => {
    const seen = new Map<string, Found>();
    for (const match of (props.item.custom_resource ?? '').matchAll(/^\s*(?:-\s*)?image:\s*["']?([^\s"'#]+)/gm)) {
        const reference = match[1];
        if (!seen.has(reference)) {
            seen.set(reference, {reference, ...split(reference)});
        }
    }
    return [...seen.values()];
});

function split(reference: string): { repository: string; tag: string } {
    const plain = reference.split('@')[0];
    const slash = plain.lastIndexOf('/');
    const colon = plain.lastIndexOf(':');
    return colon > slash ? {repository: plain.slice(0, colon), tag: plain.slice(colon + 1)} : {repository: plain, tag: 'latest'};
}

/** Docker Hub's names written either way, as the registry reads them. */
function normalized(repository: string): string {
    let name = repository.replace(/^(docker\.io|registry-1\.docker\.io)\//, '');
    return name.includes('/') ? name : `library/${name}`;
}

function imageFor(repository: string): ContainerImage | undefined {
    return images.value.find(image => normalized(image.url ?? '') === normalized(repository));
}

function isTracked(entry: Found): boolean {
    return entry.tag === VersionPlaceholder && imageFor(entry.repository)?.id === props.item.container_image_id;
}

onMounted(() => {
    load();
    bus.on('containerImageEditDialog_closed', load);
});

onUnmounted(() => {
    bus.off('containerImageEditDialog_closed', load);
});

function load() {
    Api.containerImages().get().find(items => images.value = items);
}

function onTrackClicked(entry: Found, image: ContainerImage) {
    linking.value = entry.reference;
    const api = Api.deploymentSpecifications().linkCustomResourceImagePutById(props.item.id!);
    api.setErrorHandler(response => {
        bus.emit('toast', {text: response?.error ?? 'Could not be tracked'});
        linking.value = '';
        return false;
    });
    api.save({containerImageId: image.id, image: entry.reference}, spec => {
        props.item.container_image_id = spec.container_image_id;
        props.item.custom_resource = spec.custom_resource;
        linking.value = '';
        bus.emit('deploymentSpecificationSaved', spec);
        bus.emit('toast', {text: `Tracked as ${image.name} - its deployments run ${entry.tag}`});
    });
}

function onCreateClicked(entry: Found) {
    const image = ContainerImage.Create();
    image.url = entry.repository;
    image.name = entry.repository.split('/').pop();
    bus.emit('containerImageEdit', {containerImage: image});
}
</script>

<template>
    <v-card v-if="found.length" variant="outlined" class="mt-4">
        <v-card-text>
            <div class="text-body-medium mb-1">Images it names</div>
            <div class="text-body-small text-medium-emphasis mb-3">
                Track one as the specification's container image, and it is listed under Container images, scanned for vulnerabilities and
                auto updated: its tag becomes <code>{{ VersionPlaceholder }}</code>, and each deployment runs the tag it had as its version.
            </div>
            <div v-for="entry in found" :key="entry.reference" class="d-flex align-center flex-wrap ga-2 mb-2">
                <code class="text-body-small">{{ entry.reference }}</code>
                <v-spacer/>
                <template v-if="isTracked(entry)">
                    <v-chip size="small" color="success" variant="tonal" label>
                        <v-icon start size="x-small">fa fa-check</v-icon>
                        Tracked as {{ imageFor(entry.repository)?.name }}
                    </v-chip>
                </template>
                <template v-else-if="entry.tag === VersionPlaceholder">
                    <span class="text-body-small text-medium-emphasis">version from the deployment</span>
                </template>
                <template v-else-if="imageFor(entry.repository)">
                    <v-btn
                        size="small"
                        variant="tonal"
                        color="primary"
                        :loading="linking === entry.reference"
                        @click="onTrackClicked(entry, imageFor(entry.repository)!)">
                        Track as {{ imageFor(entry.repository)?.name }}
                    </v-btn>
                </template>
                <template v-else>
                    <span class="text-body-small text-medium-emphasis">not a container image yet</span>
                    <v-btn size="small" variant="text" color="primary" @click="onCreateClicked(entry)">Create it</v-btn>
                </template>
            </div>
        </v-card-text>
    </v-card>
</template>
