<!-- This component only emits `toggle`, applying full screen is the host page's job.
     Wire it up in the Twig include:

     'events': { 'toggle': '(on) => { document.querySelector("main#main")?.classList.toggle("full-screen-mode", on); }' }

     The `.full-screen-mode` styles below are bundled into submitty-vue.css, which
     loads on every page, so no per-page CSS is needed.
-->

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const { initialFullScreen } = defineProps<{
    initialFullScreen?: boolean;
}>();

const emit = defineEmits<{
    toggle: [boolean];
}>();

const buttonRef = ref<HTMLButtonElement | null>(null);
const isFullScreen = ref(initialFullScreen);
const iconClass = computed(() => (isFullScreen.value ? 'fa-compress' : 'fa-expand'));

let targetContainer: HTMLElement | null = null;

function getContainer(): HTMLElement | null {
    return buttonRef.value?.closest('main') || buttonRef.value?.parentElement || null;
}

function onKeyDown(event: KeyboardEvent) {
    if (event.key === 'Escape' && isFullScreen.value && !event.defaultPrevented) {
        isFullScreen.value = false;
        emit('toggle', false);
    }
}

function attachContainerListener() {
    removeContainerListener();
    targetContainer = getContainer();
    targetContainer?.addEventListener('keydown', onKeyDown);
}

function removeContainerListener() {
    targetContainer?.removeEventListener('keydown', onKeyDown);
    targetContainer = null;
}

function toggle() {
    isFullScreen.value = !isFullScreen.value;
    emit('toggle', isFullScreen.value);
}

watch(
    isFullScreen,
    (active) => {
        if (active) {
            attachContainerListener();
        }
        else {
            removeContainerListener();
        }
    },
);

onMounted(() => {
    if (isFullScreen.value) {
        attachContainerListener();
    }
});

onBeforeUnmount(() => {
    removeContainerListener();
});

</script>

<template>
  <button
    id="fullscreen-btn"
    ref="buttonRef"
    data-testid="fullscreen-btn"
    class="btn btn-default"
    title="Toggle full screen mode"
    @click="toggle"
    @keydown.esc="onKeyDown"
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
