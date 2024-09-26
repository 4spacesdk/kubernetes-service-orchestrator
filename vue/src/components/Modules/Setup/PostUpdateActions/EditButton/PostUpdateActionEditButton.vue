<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue'
import {PostUpdateAction} from "@/core/services/Deploy/models";
import bus from "@/plugins/bus";

const props = defineProps<{
    postUpdateAction: PostUpdateAction
}>();

const showUpdateConditions = ref(false);

onMounted(() => {
    render();

    watch(() => props.postUpdateAction, _ => render());
});

function render() {
    showUpdateConditions.value = true;
}

function onUpdateConditionsClicked() {
    bus.emit('postUpdateActionUpdateConditions', {
        postUpdateAction: props.postUpdateAction
    });
}

</script>

<template>
    <div
        class="pa-2 w-100">
        <v-card
            class="w-100 list-wrapper">

            <v-list
                class="list-items">
                <v-list-item
                    v-if="showUpdateConditions"
                    dense
                    @click="onUpdateConditionsClicked">
                    <v-list-item-title>
                        <v-icon size="small" class="my-auto ml-2">fa fa-key</v-icon>
                        <span class="ml-2">Conditions</span>
                    </v-list-item-title>
                </v-list-item>
            </v-list>
        </v-card>
    </div>
</template>

<style scoped>
.list-wrapper {
    min-width: 120px;
}

.v-list-item {
    min-height: unset;
}

.v-list-item-title {
    font-size: 11px !important;
}

.v-progress-circular {
    margin: 1rem;
}
</style>
