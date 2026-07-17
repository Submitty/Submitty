<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

function getMain(): HTMLElement | null {
    return document.querySelector('main#main');
}

const isFullScreen = ref(getMain()?.classList.contains('full-screen-mode') ?? false);
const iconClass = computed(() => (isFullScreen.value ? 'fa-compress' : 'fa-expand'));

function setFullScreen(on: boolean) {
    const main = getMain();
    if (!main) {
        return;
    }
    main.classList.toggle('full-screen-mode', on);
    isFullScreen.value = on;
}

function toggle() {
    setFullScreen(!isFullScreen.value);
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && isFullScreen.value) {
        setFullScreen(false);
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <button
    id="fullscreen-btn"
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
