<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import type {VCard} from "vuetify/components";
import {useRoute, useRouter} from "vue-router";

// Props (One-way bindings)
const props = defineProps<{
    // foo: string
    // bar?: number
}>();

// Events (Parent can listen)
const emit = defineEmits<{
    // (e: 'change', id: number): void
    // (e: 'update', value: string): void
}>();

// Ref (Local variables)
const book = reactive({ title: 'Vue 3 Guide' });

// Computed (Variables with getter and setter)
const count = ref(0);
const double = computed(() => count.value * 2);

// Watch
watch(count, newCount => {

});

// Functions
function onButtonClickEvent(event: Event) {

}

// Template references
const card = ref<InstanceType<typeof VCard> | null>(null)

// Component life cycle
onMounted(() => {
    const route = useRoute();
    const router = useRouter();
});

</script>

<template>
    <div class="h-100">
        <v-card ref="card">
            <v-card-title>Dashboard</v-card-title>
            <v-card-text>

            </v-card-text>
        </v-card>
    </div>
</template>

<style scoped>

</style>
