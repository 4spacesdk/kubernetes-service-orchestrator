<script setup lang="ts">
import {computed, defineComponent, onMounted, reactive, ref, watch} from 'vue';
import moment from 'moment';

// Props (One-way bindings)
const props = defineProps({
    dateString: String,
    date: Date,
    textFormat: {type: String, default: 'D/M-YY HH:mm'},
    toolTipFormat: {type: String, default: 'DD/MM-YY HH:mm:ss'},
});

const text = ref('');
const toolTip = ref('');

onMounted(() => {
    render();
    watch(() => props.dateString, newWorkspace => {
        render();
    });
    watch(() => props.date, newWorkspace => {
        render();
    });
});

function render() {
    const date = props.dateString ? new Date(props.dateString) : props.date;
    text.value = moment(date).format(props.textFormat);
    toolTip.value = moment(date).format(props.toolTipFormat);
}

</script>

<template>
    <span>
        {{ text }}
        <v-tooltip activator="parent" location="bottom">{{ toolTip }}</v-tooltip>
    </span>
</template>

<style scoped>

</style>
