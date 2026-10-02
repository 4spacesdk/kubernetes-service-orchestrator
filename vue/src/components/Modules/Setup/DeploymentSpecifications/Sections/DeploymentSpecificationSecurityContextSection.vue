<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {ContainerImage, DeploymentSpecification} from "@/core/services/Deploy/models";
import {Api} from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import {SecurityContextOverrides} from "@/constants";
import {useAutoSave} from "@/composables/useAutoSave";
import PageSection from "@/components/Modules/Common/DetailPage/PageSection.vue";

/**
 * The container image's security settings, as this specification takes them: inherited, or turned
 * on or off here. The image is made secure when it is made - kso reads what it runs as - so the
 * usual answer here is to inherit, and an override is for the specification that needs to differ:
 * a version that needs root after all, or hardening the image does not have. Each container takes
 * its own image's; an override applies to them all.
 */
const props = defineProps<{
    deploymentSpecification: DeploymentSpecification
}>();

const isLoading = ref(false);
const image = ref<ContainerImage>();
const runAsNonRoot = ref(SecurityContextOverrides.Inherit);
const dropAllCapabilities = ref(SecurityContextOverrides.Inherit);
const seccompRuntimeDefault = ref(SecurityContextOverrides.Inherit);
const fsGroup = ref('');
const writablePaths = ref<string[]>([]);
const sizeLimit = ref('');

function reasonInvalid(): string | null {
    if (fsGroup.value !== '' && !/^\d+$/.test(fsGroup.value)) {
        return 'the fs group is a number';
    }
    const wrong = writablePaths.value.find(path => !path.startsWith('/') || path.includes('..') || /[\s:]/.test(path));
    if (wrong) {
        return `${wrong} is not an absolute path, such as /tmp`;
    }
    if (sizeLimit.value !== '' && !/^\d+(Ki|Mi|Gi|Ti|K|M|G|T)?$/.test(sizeLimit.value)) {
        return 'the size is one Kubernetes reads, such as 512Mi or 1Gi';
    }
    return null;
}

const {autoSave, markLoaded, saveNow} = useAutoSave({
    state: () => [runAsNonRoot.value, dropAllCapabilities.value, seccompRuntimeDefault.value, fsGroup.value, writablePaths.value.join(','), sizeLimit.value],
    validate: reasonInvalid,
    request: () => Api.deploymentSpecifications().patchById(props.deploymentSpecification.id!),
    data: () => ({
        security_context_run_as_non_root: runAsNonRoot.value,
        security_context_drop_all_capabilities: dropAllCapabilities.value,
        security_context_seccomp_runtime_default: seccompRuntimeDefault.value,
        security_context_fs_group: fsGroup.value,
        writable_paths: writablePaths.value.join(','),
        writable_paths_size_limit: sizeLimit.value,
    }),
    onSaved: saved => bus.emit('deploymentSpecificationSaved', saved),
});
defineExpose({saveNow});

onMounted(() => {
    isLoading.value = true;
    Api.deploymentSpecifications().getById(props.deploymentSpecification.id!)
        .include('container_image')
        .find(items => {
            const spec = items[0];
            image.value = spec?.container_image;
            runAsNonRoot.value = spec?.security_context_run_as_non_root ?? SecurityContextOverrides.Inherit;
            dropAllCapabilities.value = spec?.security_context_drop_all_capabilities ?? SecurityContextOverrides.Inherit;
            seccompRuntimeDefault.value = spec?.security_context_seccomp_runtime_default ?? SecurityContextOverrides.Inherit;
            fsGroup.value = String(spec?.security_context_fs_group ?? '');
            writablePaths.value = (spec?.writable_paths ?? '').split(',').map(path => path.trim()).filter(Boolean);
            sizeLimit.value = spec?.writable_paths_size_limit ?? '';
            isLoading.value = false;
            markLoaded();
        });
});

/** The three choices, with what inheriting means for this specification's image. */
function choices(imageValue: boolean | undefined) {
    return [
        {value: SecurityContextOverrides.Inherit, title: `As the container image (${imageValue ? 'on' : 'off'})`},
        {value: SecurityContextOverrides.On, title: 'On'},
        {value: SecurityContextOverrides.Off, title: 'Off'},
    ];
}

function effective(override: string, imageValue: boolean | undefined): boolean {
    return override === SecurityContextOverrides.On || (override === SecurityContextOverrides.Inherit && !!imageValue);
}

/** What the restricted profile asks for, and whether this specification's main container meets it. */
const restricted = computed(() => [
    {label: 'Does not run as root', met: effective(runAsNonRoot.value, image.value?.security_context_run_as_non_root)},
    {label: 'No privilege escalation', met: !image.value?.security_context_allow_privilege_escalation},
    {label: 'All capabilities dropped', met: effective(dropAllCapabilities.value, image.value?.security_context_drop_all_capabilities)},
    {label: 'Seccomp profile RuntimeDefault', met: effective(seccompRuntimeDefault.value, image.value?.security_context_seccomp_runtime_default)},
]);

/** The paths the specification's image says it writes to - they come along whatever is added here. */
const imagePaths = computed(() => (image.value?.writable_paths ?? '').split(',').map(path => path.trim()).filter(Boolean));

const runsAs = computed(() => {
    if (!image.value?.image_user_tag) {
        return '';
    }
    return `${image.value.name} ${image.value.image_user_tag} runs as ${image.value.image_user || 'root'}`;
});
</script>

<template>
    <page-section
        title="Security Context"
        :is-loading="isLoading"
        :auto-save="autoSave">
        <div class="section-form">
            <p class="text-medium-emphasis mb-4">
                Each container takes its own image's settings. Override them here when this specification needs to differ.
                <span v-if="runsAs"><br>{{ runsAs }}.</span>
            </p>

            <v-select
                v-model="runAsNonRoot"
                :items="choices(image?.security_context_run_as_non_root)"
                variant="outlined"
                label="Run as non-root"
                persistent-hint
                hint="Kubernetes will not start a container that would run as root. Off is the way out for a version that needs root"/>

            <v-select
                v-model="seccompRuntimeDefault"
                :items="choices(image?.security_context_seccomp_runtime_default)"
                variant="outlined"
                class="mt-4"
                label="Seccomp profile RuntimeDefault"
                persistent-hint
                hint="The runtime's default filter of system calls"/>

            <v-select
                v-model="dropAllCapabilities"
                :items="choices(image?.security_context_drop_all_capabilities)"
                variant="outlined"
                class="mt-4"
                label="Drop all capabilities"
                persistent-hint
                hint="An image that needs one - binding a port below 1024 as non-root, say - will fail"/>

            <v-text-field
                v-model="fsGroup"
                variant="outlined"
                class="mt-4"
                label="FS Group"
                persistent-hint
                :placeholder="String(image?.security_context_fs_group ?? '')"
                hint="One group for all this specification's pods, kept when an image's user changes, so a new version can still write what an old one left on a volume. Empty is the container image's"/>

            <v-combobox
                v-model="writablePaths"
                multiple
                chips
                closable-chips
                variant="outlined"
                class="mt-4"
                label="Writable paths"
                placeholder="/var/cache/app"
                persistent-hint
                :hint="imagePaths.length ? `More, for this specification's workload - its images' own come along: ${imagePaths.join(', ')}` : 'Where its workload writes when the image\'s root filesystem is read-only - an empty directory on each. Its images\' own come along'"/>

            <v-text-field
                v-model="sizeLimit"
                variant="outlined"
                class="mt-4"
                label="Size of each writable path"
                placeholder="1Gi"
                persistent-hint
                hint="The most each may hold - on disk, not in memory. Empty is 1Gi"/>

            <div class="mt-6">
                <div class="text-body-medium mb-2">
                    Restricted profile: {{ restricted.filter(rule => rule.met).length }} of {{ restricted.length }}
                </div>
                <div
                    v-for="rule in restricted"
                    :key="rule.label"
                    class="d-flex align-center ga-2 mb-1">
                    <v-icon
                        size="small"
                        :color="rule.met ? 'success' : 'grey'">
                        {{ rule.met ? 'fa fa-circle-check' : 'fa fa-circle-minus' }}
                    </v-icon>
                    <span>{{ rule.label }}</span>
                </div>
            </div>
        </div>
    </page-section>
</template>

<style scoped>
.section-form {
    max-width: 720px;
}
</style>
