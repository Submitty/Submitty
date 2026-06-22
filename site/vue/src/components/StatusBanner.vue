<script setup lang="ts">
import { onMounted } from 'vue';

const props = defineProps<{
    message: string;
    color: string;
}>();

const emit = defineEmits<{
    'color-change': [color: string];
}>();

onMounted(() => {
    emit('color-change', props.color);
    window.dispatchEvent(new CustomEvent('status-banner-color-change', {
        detail: props.color,
    }));
});
</script>

<template>
    <div
        id="bar_banner"
        class="banner"
        :style="{ 'background-color': color, color: 'black' }"
        data-testid="status-banner"
    >
        {{ message }}
    </div>
</template>
