<!-- This component only emits `toggle`, applying full screen is the host page's job.
     Wire it up in the Twig include:

     'events': { 'toggle': '(on) => { document.querySelector("main#main")?.classList.toggle("full-screen-mode", on); }' }

     The `.full-screen-mode` styles below are bundled into submitty-vue.css, which
     loads on every page, so no per-page CSS is needed.
-->

<script setup lang="ts">
import { ref, computed, watch } from 'vue';

const props = defineProps<{
    initialFullScreen?: boolean;
    isFullScreen?: boolean;
}>();

const emit = defineEmits<{
    toggle: [boolean];
}>();

const internalFullScreen = ref(props.initialFullScreen ?? false);

watch(
    () => props.isFullScreen,
    (val) => {
        if (val !== undefined) {
            internalFullScreen.value = val;
        }
    },
);

const active = computed(() =>
    props.isFullScreen !== undefined ? props.isFullScreen : internalFullScreen.value,
);

const iconClass = computed(() => (active.value ? 'fa-compress' : 'fa-expand'));

function toggle() {
    const next = !active.value;
    internalFullScreen.value = next;
    emit('toggle', next);
}

function onEscape() {
    if (!active.value) {
        return;
    }
    internalFullScreen.value = false;
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
