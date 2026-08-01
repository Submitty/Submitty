<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

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

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && isFullScreen.value) {
        isFullScreen.value = false;
        emit('toggle', false);
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
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

<style>
.full-screen-mode {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    margin: 0;
    z-index: 10;
}

.full-screen-mode .content {
    margin: 0;
}
</style>
