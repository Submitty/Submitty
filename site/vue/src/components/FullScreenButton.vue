<!-- This component only emits `toggle`, applying full screen is the host page's job.
     Wire it up in the Twig include:

     'events': { 'toggle': '(on) => { document.querySelector("main#main")?.classList.toggle("full-screen-mode", on); }' }

     The `.full-screen-mode` styles below are bundled into submitty-vue.css, which
     loads on every page, so no per-page CSS is needed.

     Pressing escape to exit fullscreen only works when the button is in focus due to our current Vue standards and setup.
-->

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

function onEscape() {
    if (!isFullScreen.value) {
        return;
    }
    isFullScreen.value = false;
    emit('toggle', false);
}

</script>

<template>
  <button
    id="fullscreen-btn"
    data-testid="fullscreen-btn"
    class="btn btn-default"
    title="Toggle full screen mode"
    @click="toggle"
    @keydown.esc="onEscape"
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
