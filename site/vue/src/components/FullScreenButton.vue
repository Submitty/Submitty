<script setup lang="ts">
import { ref, computed } from 'vue';

const { initialFullScreen } = defineProps<{
    initialFullScreen?: boolean;
}>();

const emit = defineEmits<{
    toggle: [boolean];
}>();

const isFullScreen = ref(initialFullScreen);
const iconClass = computed(() => (isFullScreen.value ? 'fa-compress' : 'fa-expand'));

function toggle() {
    isFullScreen.value = !isFullScreen.value;
    emit('toggle', isFullScreen.value);
}
</script>

<template>
  <button
    id="fullscreen-btn"
    data-testid="fullscreen-btn"
    class="btn btn-default"
    title="Toggle full screen mode"
    @click="toggle"
  >
    <i
      class="fas"
      :class="iconClass"
      aria-hidden="true"
    />
  </button>
</template>
