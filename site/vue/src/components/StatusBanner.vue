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
});
</script>

<template>
  <div
    id="bar_banner"
    class="banner"
    :style="{ 'background-color': color, color: 'black' }"
    data-testid="bar-banner"
  >
    {{ message }}
  </div>
</template>
